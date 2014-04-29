<?php

class Datarec_Exporter_Helper_Data extends Mage_Core_Helper_Abstract {

    /**
     * Contains current collection
     * @var string
     */
    protected $_io = null;

    function create_file($name, $format) {
        $this->_io = new Varien_Io_File();
        $path = Mage::getBaseDir('media') . DS . 'export' . DS;
        $file = $path . DS . $name.'.'.$format;

        $this->_io->setAllowCreateFolders(true);
        $this->_io->open(array('path' => $path));
        $this->_io->streamOpen($file, 'w+');
        $this->_io->streamLock(true);
        return $file;
    }

    function save_file($file, $content, $format) {
        if($this->_io) {
            if ($format == "csv") {
                $this->_io->streamWrite($content);
            }
            else if ($format == "json") {
                $this->_io->streamWrite(json_encode($content));
            }
            return array(
                'type' => 'filename',
                'value' => $file,
                'rm' => false //keep as cache (if necessary)
            );
        }
    }

    function get_query($query) {
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        return $readConnection->fetchAll($query);
    }

    function controllerExportData($filename, $exportfunction) {
        set_time_limit(0);
        ini_set('memory_limit', '4096M');

        //GET params
        $forcegeneration = 0;
        if (isset($_GET["forcegen"]) && $_GET["forcegen"] == "true") {
            $forcegeneration = 1;
        }
        //File path
        $path = Mage::getBaseDir('media') . DS . 'export' . DS;
        $file = $path . DS . $filename . '.json';

        //Cache
        if ((file_exists($file) && (time() - filemtime($file)) < (60 * 60 * 24)) && !$forcegeneration) {
            error_log("Export -> cache file served");
            $content = array(
                'type' => 'filename',
                'value' => $file,
                'rm' => false //keep as cache (if necessary)
            );
        } else {
            error_log("Datarec export -> creating new file for export");
            if (file_exists($file))
                unlink($file);
            if ($exportfunction == 'exportLikes'){
                $content = Mage::getModel('datarec_exporter/resource')->$exportfunction($_GET["type"]);
            }
            else {
                $content = Mage::getModel('datarec_exporter/resource')->$exportfunction();
            }
        }

        return $content;
    }

}
