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

}
