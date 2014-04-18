<?php

class Beautyst_Exporter_Helper_Data extends Mage_Core_Helper_Abstract {

    /**
     * Contains current collection
     * @var string
     */
    protected $_list = null;
    protected $_io = null;

    public function __construct() {
        //
    }

    public function initProductExport() {
        $idTB = Mage::helper("importxml")->getConf(Beautyst_ImportXml_Model_Config::XML_PATH_TB_PROVIDER_ID);
        $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToSelect('*')
                ->addFieldToFilter('status', 1);
        $collection->addFieldToFilter(array(
            array('attribute' => 'provider', 'eq' => $idTB),
        ));

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
    public function generateCsvList() {
        //Init
        $this->initProductExport();

        //Fields
        $fields = array(
            "ID" => "id",
            "SKU" => "sku",
            "MANUFACTURER" => "manufacturer", //brand
            "NAME" => "name",
            "DESCRIPTION" => "description",
            "SHORT_DESCRIPTION" => "short_description",
            "PRICE" => "price",
            "PRICE_PROMO" => "special_price",
            "PROMO_FROM" => "special_from_date",
            "PROMO_TO" => "special_to_date",
            "STOCK" => "is_in_stock",
            "URL" => "", //base + url_path
            "IMAGE" => "image", // base + image
            "CATEGORY" => "",
            "ATT_CAPACITY" => "capacity",
            "ATT_EAN" => "ean",
            "ATT_PROVIDER" => "provider",
            "ATT_SHADE" => "shade",
            "ATT_SHADE_IMAGE" => "shade_image"//Avec url magento devant
        );

        $idBrands = Mage::getStoreConfig(Beautyst_Catalog_Model_Category::XML_PATH_BRAND_CATEGORY_ID);

        if (!is_null($this->_list)) {
            $items = $this->_list->getItems();
            if (count($items) > 0) {

                $io = new Varien_Io_File();
                $path = Mage::getBaseDir('media') . DS . 'export' . DS;
                $file = $path . DS . 'product_list.csv';

                //File creation
                $io->setAllowCreateFolders(true);
                $io->open(array('path' => $path));
                $io->streamOpen($file, 'w+');
                $io->streamLock(true);

                //HEADER
                //$io->streamWriteCsv($this->_getCsvHeaders($items));
                $io->streamWrite(implode("|", array_keys($fields)) . "\n");
                //CONTENT
                foreach ($items as $product) {
                    $lineCsv = array();
                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
                    $strCats = array();
                    $currentCatIds = $product->getCategoryIds();

                    if (!empty($currentCatIds)) {
                        $categoryCollection = Mage::getResourceModel('catalog/category_collection')
                                ->addAttributeToSelect('name')
                                ->addAttributeToFilter('entity_id', $currentCatIds)
                                ->addIsActiveFilter();

                        foreach ($categoryCollection as $cat) {
                            if ($cat->getLevel() > 1 && $cat->getLevel() < 5 && $cat->getId() != $idBrands && $cat->getParentId() != $idBrands)
                                $strCats[] = htmlentities($cat->getName());
                        }
                    }

                    foreach ($fields as $column => $attr) {
                        //Special cases first (manufacturer, stock/qty, url, image, FDP...)
                        if ($column == "MANUFACTURER")
                            $lineCsv[] = $product->getAttributeText('brand_id');
                        else if ($column == "CATEGORY")
                            $lineCsv[] = implode(", ", $strCats);
                        else if ($column == "ATT_CAPACITY")
                            $lineCsv[] = $product->getAttributeText('capacity');
                        else if ($column == "ATT_PROVIDER")
                            $lineCsv[] = $product->getAttributeText('provider');
                        else if ($column == "ATT_SHADE")
                            if ($product->getAttributeText('shade') != "")
                                $lineCsv[] = htmlentities($product->getAttributeText('shade'));
                            else
                                $lineCsv[] = "non";
                        else if (($column == "ATT_SHADE_IMAGE" || $column == "IMAGE"))
                            if ("no_selection" !== (string) $product->getData($attr))
                                $lineCsv[] = Mage::getModel('catalog/product_media_config')->getMediaUrl($product->getData($attr));
                            else
                                $lineCsv[] = "non";
                        else if ($column == "URL")
                            $lineCsv[] = Mage::getBaseUrl() . $product->getUrlPath();
                        else
                            $lineCsv[] = preg_replace('/\r\n/', ' ', str_replace("|", "", trim(htmlentities(strip_tags($product->getData($attr))))));
                    }

                    $io->streamWrite(implode("|", $lineCsv) . "\n");
                }

                return array(
                    'type' => 'filename',
                    'value' => $file,
                    'rm' => false //keep as cache (if necessary)
                );
            }
        }
    }

    function get_query($query) {
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        return $readConnection->fetchAll($query);
    }

    function exportLiked($type, $format = "csv") {
        //Get liked content
        //Get all users
        //Filter by type (+ get magento id for the products)
        if ($type == "")
            die("Spécifier un type de produits");

        $tabusers = $this->get_query('select ID, user_email from wp_users where 1;');

        $jsonTab = array();
        $csvTxt = "";

        foreach ($tabusers as $user) {

            if ($type == "produits")
                $query = 'SELECT m.meta_value as postid from `clrz_likes` c INNER JOIN wp_posts w ON (c.post_id = w.ID) LEFT JOIN wp_postmeta m ON (c.post_id = m.post_id AND m.meta_key="magento_id") WHERE w.post_type = "' . $type . '" AND c.user_id = "' . $user["ID"] . '" AND w.post_status="publish";';
            else
                $query = 'SELECT w.ID as postid from `clrz_likes` c INNER JOIN wp_posts w ON (c.post_id = w.ID) WHERE w.post_type = "' . $type . '" AND c.user_id = "' . $user["ID"] . '" AND w.post_status="publish";';

            $tablikes = $this->get_query($query);

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
            $tabViews = $this->get_query($query);
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

    function exportOrdered($format = "csv") {
        // File Creation///////
        $file = $this->create_file("orders", $format);
        ///////////////////////

        $collection = Mage::getModel('customer/customer')
                ->getCollection()
                ->setPage(1,4)
                ->addAttributeToSelect('*');

        $jsonTab = array();
        $csvTxt = "";        

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
                        if ($prod->getStatus() === "1")
                            $tabProduct = array();
                            $tabProduct["product_id"] = $item->getProductId();
                            $tabProduct["price"] = $prod->getPrice();
                            $tabProduct["quantity"] = $item->getQtyToShip();
                            if ($format == "csv") {
                                $tabOrder["products"][] = implode("/", $tabProduct);
                            }
                            else if ($format == "json") {
                                $tabOrder["products"][] = $tabProduct;
                            }
                    }
                    if ($format == "csv") {
                        $tabOrders[] = implode("|", $tabOrder);
                    }
                    else if ($format == "json") {
                        $tabOrders[] = $tabOrder;
                    }
                }
            }

            if (!empty($tabOrders)) {
                if ($format == "csv") {
                    $tabRes = array(
                        $customer->getId(),
                        $customer->getEmail(),
                        $customer->getLastName(),
                        $customer->getFirstName(),
                        implode(",", $tabOrders)
                    );
                    $csvTxt .= implode(";", $tabRes) . "\n";
                } else if ($format == "json") {
                    $jsonTab[] = array("user_id" => $customer->getId(),
                        "email" => $customer->getEmail(),
                        "last_name" => $customer->getLastName(),
                        "first_name" => $customer->getFirstName(),
                        "orders" => $tabOrders
                    );
                }
            }
        }

        if ($format == "csv") {
            return $this->save_file($file, $csvTxt, $format);
        }
        else if ($format == "json" && !empty($jsonTab)) {
            return $this->save_file($file, $jsonTab, $format);
        }
    }

    function create_file($name, $format) {
        $this->$_io = new Varien_Io_File();
        $path = Mage::getBaseDir('media') . DS . 'export' . DS;
        $file = $path . DS . $name.'.'.$format;

        $this->$_io->setAllowCreateFolders(true);
        $this->$_io->open(array('path' => $path));
        $this->$_io->streamOpen($file, 'w+');
        $this->$_io->streamLock(true);
        return $file;
    }

    function save_file($file, $content, $format) {
        if(!$this->$_io) {
            if ($format == "csv") {
                $this->$_io->streamWrite($content);
            }
            else if ($format == "json") {
                $this->$_io->streamWrite(json_encode($content));
            }
            return array(
                'type' => 'filename',
                'value' => $file,
                'rm' => false //keep as cache (if necessary)
            );
        }
    }

}
