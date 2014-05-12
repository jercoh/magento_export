<?php

class Datarec_Exporter_PostsController extends Mage_Core_Controller_Front_Action {
    public function indexAction() {
        //params: type of content (videos, posts, photos, products
        $allowedType = array("videos", "products", "photos", "post");
        $type = (isset($_GET["type"]) && $_GET["type"] != "") ? $_GET["type"] : "";

        if ($type != "" && in_array($_GET["type"], $allowedType)) {
            $filename = 'datarec_posts_'.$type;
            $methodename = 'exportPosts';
            $content = Mage::helper("datarec_exporter/data")->controllerExportData($filename, $methodename);
            $this->_prepareDownloadResponse($filename . '.json', $content);
        } else {
            die("Erreur veuillez préciser un type de contenu valide...");
        }
    }
}