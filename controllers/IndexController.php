<?php

class Beautyst_Exporter_IndexController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        set_time_limit(0);
        ini_set('memory_limit', '4096M');

        //GET params
        $forcegeneration = 0;
        if (isset($_GET["forcegen"]) && $_GET["forcegen"] == "true") {
            $forcegeneration = 1;
        }

        //File path
        $path = Mage::getBaseDir('media') . DS . 'export' . DS;
        $file = $path . DS . 'lengow.csv';

        //Cache
        if ((file_exists($file) && (time() - filemtime($file)) < (60 * 60 * 24)) && !$forcegeneration) {
            error_log("Lengow export -> cache file served");
            $content = array(
                'type' => 'filename',
                'value' => $file,
                'rm' => false //keep as cache (if necessary)
            );
        } else {
            error_log("Lengow export -> creating new file for export");
            if (file_exists($file))
                unlink($file);
            $content = Mage::helper('exporter')->generateCsvList();
        }

        $filename = "lengow.csv";
        $this->_prepareDownloadResponse($filename, $content);
    }

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

    public function get_content_by_type($action, $type, $format) {
        $helper = Mage::helper("exporter");
        if ($action == "liked") {
            return $helper->exportLiked($type, $format);
        } else if ($action == "viewed") {
            return $helper->exportViewed($type, $format);
        } else if ($action == "ordered") {
            return $helper->exportOrdered($format);
        }
    }

    public function get_content($action, $type, $format, $forcegen) {
        if ($this->is_cache_avail($action, $type, $format) && !$forcegen) {
            return $this->get_cache($action, $type, $format);
        } else {
            $content = $this->get_content_by_type($action, $type, $format);
            $this->write_cache($action, $type, $format, $content);
            return $content;
        }
    }

    public function likedAction() {
        //params: type of content (videos, posts, photos, products
        //format: json/csv

        set_time_limit(0);
        ini_set('memory_limit', '4096M');

        $allowedType = array("videos", "produits", "photos", "post");
        $type = (isset($_GET["type"]) && $_GET["type"] != "") ? $_GET["type"] : "";
        $format = (isset($_GET["format"]) && $_GET["format"] != "") ? $_GET["format"] : "csv";
        $forcegen = (isset($_GET["forcegen"]) && $_GET["forcegen"] == "true");

        if (($type != "" && in_array($_GET["type"], $allowedType)) && ($format == "csv" || $format == "json")) {
            echo $this->get_content("liked", $type, $format, $forcegen);
            die;
        } else {
            die("Erreur...");
        }
    }

    public function viewedAction() {
        //params: type of content (only works with products for now)
        //format: json/csv

        set_time_limit(0);
        ini_set('memory_limit', '4096M');

        $type = (isset($_GET["type"]) && $_GET["type"] != "") ? $_GET["type"] : "produits";
        $format = (isset($_GET["format"]) && $_GET["format"] != "") ? $_GET["format"] : "csv";
        $forcegen = (isset($_GET["forcegen"]) && $_GET["forcegen"] == "true");

        if ($type != "" && ($format == "csv" || $format == "json")) {
            echo $this->get_content("viewed", $type, $format, $forcegen);
            die;
        } else {
            die("Erreur...");
        }
    }

    public function orderedAction() {
        //only for products so no params required
        //format: json/csv

        set_time_limit(0);
        ini_set('memory_limit', '4096M');
        $helper = Mage::helper("exporter");

        $format = (isset($_GET["format"]) && $_GET["format"] != "") ? $_GET["format"] : "csv";
        $type = "produits";
        $forcegen = (isset($_GET["forcegen"]) && $_GET["forcegen"] == "true");

        if ($format == "csv" || $format == "json") {
            echo $this->get_content("ordered", $type, $format, $forcegen);
            die;
        } else {
            die("Erreur...");
        }
    }

}
