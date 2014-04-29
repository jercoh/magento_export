<?php

class Datarec_Exporter_IndexController extends Mage_Core_Controller_Front_Action {

    public function is_cache_avail($action, $type, $format) {
        $key = md5($action . $type . $format);
        $path = Mage::getBaseDir('media') . DS . 'export' . DS;
        $file = $path . $key;

        return (file_exists($file) && (time() - filemtime($file)) < (60 * 60 * 12));
    }

    public function get_cache($action, $type, $format) {
        $key = md5($action . $type . $format);
        $path = Mage::getBaseDir('media') . DS . 'export' . DS;
        $file = $path . $key;

        if (file_exists($file)) {
            return file_get_contents($file);
        }
    }

    public function write_cache($action, $type, $format, $content) {
        $key = md5($action . $type . $format);
        $path = Mage::getBaseDir('media') . DS . 'export' . DS;
        $file = $path . $key;

        if (file_exists($file)) {
            unlink($file);
        }

        return file_put_contents($file, $content);
    }

    // public function get_content_by_type($action, $type, $format) {
    //     $model = Mage::model("datarec_exporter");
    //     if ($action == "liked") {
    //         return $model->exportLiked($type, $format);
    //     } 
    // }

    // public function get_content($action, $type, $format, $forcegen) {
    //     if ($this->is_cache_avail($action, $type, $format) && !$forcegen) {
    //         return $this->get_cache($action, $type, $format);
    //     } else {
    //         $content = $this->get_content_by_type($action, $type, $format);
    //         $this->write_cache($action, $type, $format, $content);
    //         return $content;
    //     }
    // }

    public function indexAction() {
        $filename = 'datarec_products';
        $methodename = 'exportProducts';
        $content = Mage::helper("datarec_exporter/data")->controllerExportData($filename, $methodename);
        $this->_prepareDownloadResponse($filename . '.json', $content);
    }

    public function viewsAction() {
        $filename = 'datarec_views';
        $methodename = 'exportViews';
        $content = Mage::helper("datarec_exporter/data")->controllerExportData($filename, $methodename);
        $this->_prepareDownloadResponse($filename . '.json', $content);
    }

    public function ordersAction() {
        $filename = 'datarec_orders';
        $methodename = 'exportPurchases';
        $content = Mage::helper("datarec_exporter/data")->controllerExportData($filename, $methodename);
        $this->_prepareDownloadResponse($filename . '.json', $content);
    }

    public function likesAction() {
        //params: type of content (videos, posts, photos, products
        $allowedType = array("videos", "products", "photos", "post");
        $type = (isset($_GET["type"]) && $_GET["type"] != "") ? $_GET["type"] : "";

        if ($type != "" && in_array($_GET["type"], $allowedType)) {
            $filename = 'datarec_likes_'.$type;
            $methodename = 'exportLikes';
            $content = Mage::helper("datarec_exporter/data")->controllerExportData($filename, $methodename);
            $this->_prepareDownloadResponse($filename . '.json', $content);
        } else {
            die("Erreur veuillez préciser un type de contenu valide...");
        }
    }
}
