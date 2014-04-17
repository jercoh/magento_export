<?php
/**
 * Datarec export controller
 *
 * @category    Datarec
 * @package     Datarec_Export
 * @author      Jeremie Cohen <jeremie@datarec.io>
 * @copyright   2014 Datarec.io 
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Datarec_Export_Model_Generate extends Mage_Core_Helper_Abstract {

	/**
     * Contains current collection
     * @var string
     */
    protected $_list = null;

    public function __construct() {
        //
    }

	function exportProducts($format = "json") {
		//Init
        $this->initLengowExport();
        $jsonTab = array();

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
	}


	function exportOrders($format = "csv") {

        $collection = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToSelect('*');

        $jsonTab = array();
        $csvTxt = "";        

        foreach ($collection as $customer) {

            $orders = Mage::getModel('sales/order')->getCollection()
                    ->addAttributeToSelect('customer_email')
                    ->addAttributeToSelect('status')
                    ->addFieldToFilter('status', 'complete')
                    ->addFieldToFilter('customer_id', array('eq' => array($customer->getId())));
            ;

            $tabOrders = array();

            if(! empty($orders)){
                foreach ($orders as $order) {
                    $items = $order->getAllItems();
                    foreach ($items as $item) {
                        $prod = Mage::getModel('catalog/product')->load($item->getProductId());
                        if ($prod->getStatus() === "1")
                            $tabOrders[] = $item->getProductId();
                    }
                }
            }

            if (!empty($tabOrders)) {
                if ($format == "csv") {
                    $csvTxt .= $customer->getId() . ";" . $customer->getEmail() . ";" . implode("|", $tabOrders) . "\n";
                } else if ($format == "json") {
                    $jsonTab[] = array($customer->getId(), $customer->getEmail(), $tabOrders);
                }
            }
        }

        if ($format == "csv") {
            return $csvTxt;
        }else if ($format == "json" && !empty($jsonTab)) {
            return json_encode($jsonTab);
        }
    }
}
