<?php

class Datarec_Exporter_UsersController extends Mage_Core_Controller_Front_Action {    
    public function viewsAction() {
        $filename = 'datarec_views';
        $methodename = 'exportViews';
        $content = Mage::helper("datarec_exporter/data")->controllerExportData($filename, $methodename);
        $this->_prepareDownloadResponse($filename . '.json', $content);
    }

    public function purchasesAction() {
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