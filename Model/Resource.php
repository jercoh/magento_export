<?php

class Datarec_Exporter_Model_Resource extends Mage_Core_Model_Abstract {

    /**
     * Contains current collection
     * @var string
     */
    protected $_list = null;

    public function __construct() {
        //
    }

    public function initProductExport() {
        $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToSelect('*')
                ->addFieldToFilter('status', 1);
        $this->setList($collection);
    }

    /**
     * Sets current collection
     * @param $query
     */
    public function setList($collection) {
        $this->_list = $collection;
    }

    /**
     * Generates CSV file with product's list according to the collection in the $this->_list
     * @return array
     */
    public function exportProducts() {
        //Init
        $this->initProductExport();

        //Fields
        $fields = array(
            "id",
            "sku",
            "manufacturer", //brand
            "name",
            "description",
            "short_description",
            "price",
            "is_in_stock",
            "url", //base + url_path
            "image", // base + image
            "category",
            "capacity",
            "ean",
            "provider",
            "shade",
            "shade_image",//Avec url magento devant
            "total_sales"
        );

        if (!is_null($this->_list)) {
            $items = $this->_list->getItems();
            if (count($items) > 0) {

                $file = Mage::helper("datarec_exporter/data")->create_file("datarec_products", 'json');

                //CONTENT
                $jsonTab = array();

                foreach ($items as $product) {
                    $productJson = array();
                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
                    $strCats = array();
                    $currentCatIds = $product->getCategoryIds();

                    if (!empty($currentCatIds)) {
                        $categoryCollection = Mage::getResourceModel('catalog/category_collection')
                                ->addAttributeToSelect('name')
                                ->addAttributeToFilter('entity_id', $currentCatIds)
                                ->addIsActiveFilter();

                        foreach ($categoryCollection as $cat) {
                            if ($cat->getLevel() > 1 && $cat->getLevel() < 5 )
                                $strCats[] = htmlentities($cat->getName());
                        }
                    }

                    $productCollection = Mage::getResourceModel('reports/product_collection')
                        ->addOrderedQty()
                        ->addAttributeToFilter('sku', $product->getData('sku'))
                        ->setOrder('ordered_qty', 'desc')
                        ->getFirstItem();

                    foreach ($fields as $attr) {
                        //Special cases first (manufacturer, stock/qty, url, image, FDP...)
                        if ($attr == "manufacturer")
                            $productJson[$attr] = $product->getAttributeText('brand_id');
                        else if ($attr == "category")
                            $productJson[$attr] = implode(", ", $strCats);
                        else if ($attr == "id")
                            $productJson[$attr] = $product->getId();
                        else if ($attr == "capacity" || $attr == "provider")
                            $productJson[$attr] = $product->getAttributeText($attr);
                        else if ($attr == "shade")
                            if ($product->getAttributeText($attr) != "")
                                $productJson[$attr] = htmlentities($product->getAttributeText($attr));
                            else
                                $productJson[$attr] = "non";
                        else if ($attr == "image" || $attr == "shade_image")
                            if ("no_selection" !== (string) $product->getData($attr))
                                $productJson[$attr] = Mage::getModel('catalog/product_media_config')->getMediaUrl($product->getData($attr));
                            else
                                $productJson[$attr] = "non";
                        else if ($attr == "url")
                            $productJson[$attr] = $product->getProductUrl();
                        else if ($attr == "total_sales")
                            $productJson[$attr] = (int)$productCollection->ordered_qty;
                        else
                            $productJson[$attr] = preg_replace('/\r\n/', ' ', str_replace("|", "", trim(htmlentities(strip_tags($product->getData($attr))))));
                    }

                    $jsonTab[] = $productJson;
                }
                return Mage::helper("datarec_exporter/data")->save_file($file, $jsonTab, 'json');
            }
        }
    }

    function exportLiked($type, $format = "csv") {
        //Get liked content
        //Get all users
        //Filter by type (+ get magento id for the products)
        if ($type == "")
            die("Spécifier un type de produits");

        $tabusers = Mage::helper("datarec_exporter/data")->get_query('select ID, user_email from wp_users where 1;');

        $jsonTab = array();
        $csvTxt = "";

        foreach ($tabusers as $user) {

            if ($type == "produits")
                $query = 'SELECT m.meta_value as postid from `clrz_likes` c INNER JOIN wp_posts w ON (c.post_id = w.ID) LEFT JOIN wp_postmeta m ON (c.post_id = m.post_id AND m.meta_key="magento_id") WHERE w.post_type = "' . $type . '" AND c.user_id = "' . $user["ID"] . '" AND w.post_status="publish";';
            else
                $query = 'SELECT w.ID as postid from `clrz_likes` c INNER JOIN wp_posts w ON (c.post_id = w.ID) WHERE w.post_type = "' . $type . '" AND c.user_id = "' . $user["ID"] . '" AND w.post_status="publish";';

            $tablikes = Mage::helper("datarec_exporter/data")->get_query($query);

            if (!empty($tablikes)) {
                $list = array();
                foreach ($tablikes as $l) {
                    if (isset($l["postid"]) && $l["postid"] != "") {
                        if ($type == "produits") {
                            $prod = Mage::getModel('catalog/product')->load($l["postid"]);
                            if ($prod->getStatus() === "1")
                                $list[] = $l["postid"];
                        }else {
                            $list[] = $l["postid"];
                        }
                    }
                }

                if ($format == "csv") {
                    $tabRes = array(
                        $user["ID"],
                        $user["user_email"],
                        $user["user_lastname"],
                        $user["user_firstname"],
                        implode("|", $list)
                    );
                    $csvTxt .= implode(";", $tabRes) . "\n";
                } else if ($format == "json") {
                    $jsonTab[] = array(
                        "user_id" => $user["ID"],
                        "email" => $use["user_email"],
                        "last_name" => $user["user_lastname"],
                        "first_name" => $user["user_firstname"],
                        "views" => $list
                    );
                }
            }
        }

        //Return
        if ($format == "csv") {
            return $csvTxt;
        } else if ($format == "json") {
            return json_encode($jsonTab);
        }
    }

    function exportViewed($type = "produits", $format = "csv") {

        $collection = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToSelect('*');

        $jsonTab = array();
        $csvTxt = "";

        foreach ($collection as $customer) {

            $query = 'SELECT distinct(product_id)  FROM `report_viewed_product_index` WHERE `customer_id` = "' . $customer->getId() . '";';
            $tabViews = Mage::helper("datarec_exporter/data")->get_query($query);
            $list = array();

            foreach ($tabViews as $view) {
                $prod = Mage::getModel('catalog/product')->load($view["product_id"]);
                if ($prod->getStatus() === "1")
                    $list[] = $view["product_id"];
            }

            if (!empty($list)) {
                if ($format == "csv") {
                    $tabRes = array(
                        $customer->getId(),
                        $customer->getEmail(),
                        $customer->getLastName(),
                        $customer->getFirstName(),
                        implode("|", $list)
                    );
                    $csvTxt .= implode(";", $tabRes) . "\n";
                } else if ($format == "json") {
                    $jsonTab[] = array(
                        "user_id" => $customer->getId(),
                        "email" => $customer->getEmail(),
                        "last_name" => $customer->getLastName(),
                        "first_name" => $customer->getFirstName(),
                        "views" => $list
                    );
                }
            }
        }

        if ($format == "csv") {
            return $csvTxt;
        } else if ($format == "json") {
            return json_encode($jsonTab);
        }
    }

    function exportOrdered() {
        // File Creation///////
        $file = Mage::helper("datarec_exporter/data")->create_file("orders", 'json');
        ///////////////////////

        $collection = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToSelect('*');

        $jsonTab = array();      

        foreach ($collection as $customer) {

            $orders = Mage::getModel('sales/order')->getCollection()
                    ->addAttributeToSelect('customer_email')
                    ->addAttributeToSelect('status')
                    ->addAttributeToSelect('created_at')
                    ->addFieldToFilter('status', 'complete')
                    ->addFieldToFilter('customer_id', array('eq' => array($customer->getId())));
            ;

            $tabOrders = array();

            if(! empty($orders)){
                foreach ($orders as $order) {
                    $tabOrder = array();
                    $tabOrder["created_at"] = $order->getCreatedAtFormated('short');
                    $tabOrder["products"] = array();
                    $items = $order->getAllItems();
                    foreach ($items as $item) {
                        $prod = Mage::getModel('catalog/product')->load($item->getProductId());
                        if ($prod->getStatus() == 1){
                            $tabProduct = array();
                            $tabProduct["product_id"] = $item->getProductId();
                            $tabProduct["price"] = $prod->getPrice();
                            $tabProduct["quantity"] = $item->getQtyToShip();
                            $tabOrder["products"][] = $tabProduct;
                        }
                    }
                    $tabOrders[] = $tabOrder;
                }
            }

            if (!empty($tabOrders)) {
                $jsonTab[] = array("user_id" => $customer->getId(),
                    "email" => $customer->getEmail(),
                    "last_name" => $customer->getLastName(),
                    "first_name" => $customer->getFirstName(),
                    "orders" => $tabOrders
                );
            }
        }
        return Mage::helper("datarec_exporter/data")->save_file($file, $jsonTab, 'json');
    }

}
