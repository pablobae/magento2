<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Theme\Model\Design\Backend;

use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use \Magento\Config\Model\Config\Backend\File as BackendFile;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Theme\Model\Design\Config\FileUploader\Config;

class File extends BackendFile
{
    /**
     * @var Config
     */
    protected $fileConfig;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param UploaderFactory $uploaderFactory
     * @param RequestDataInterface $requestData
     * @param Filesystem $filesystem
     * @param Config $fileConfig
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        UploaderFactory $uploaderFactory,
        RequestDataInterface $requestData,
        Filesystem $filesystem,
        Config $fileConfig,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $uploaderFactory,
            $requestData,
            $filesystem,
            $resource,
            $resourceCollection,
            $data
        );
        $this->fileConfig = $fileConfig;
    }

    /**
     * Save uploaded file and remote temporary file before saving config value
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $values = $this->getValue();
        $value = reset($values) ?: [];
        if (!isset($value['file'])) {
             throw new LocalizedException(
                 __('%1 does not contain field \'file\'', $this->getData('field_config/field'))
             );
        }
        if (isset($value['exists'])) {
            $this->setValue($value['file']);
            return $this;
        }
        $filename = $value['file'];
        $result = $this->_mediaDirectory->copyFile(
            $this->fileConfig->getTmpMediaPath($filename),
            $this->_getUploadDir() . '/' . $filename
        );
        if ($result) {
            $this->_mediaDirectory->delete($this->fileConfig->getTmpMediaPath($filename));
            if ($this->_addWhetherScopeInfo()) {
                $filename = $this->_prependScopeInfo($filename);
            }
            $this->setValue($filename);
        } else {
            $this->unsValue();
        }

        return $this;
    }

    /**
     * @return array
     */
    public function afterLoad()
    {
        $value = $this->getValue();
        if ($value && !is_array($value)) {
            $fileName = $this->_getUploadDir() . '/' . $value;
            $stat = $this->_mediaDirectory->stat($fileName);
            $this->setValue([
                [
                    'url' => $this->fileConfig->getStoreMediaUrl() .  $fileName,
                    'file' => $value,
                    'size' => is_array($stat) ? $stat['size'] : 0,
                    'exists' => true
                ]
            ]);
        }
        return $this;
    }

    /**
     * Return path to directory for upload file
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getUploadDir()
    {
        $fieldConfig = $this->getFieldConfig();

        if (!array_key_exists('upload_dir', $fieldConfig)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('The base directory to upload file is not specified.')
            );
        }

        $uploadDir = (string)$fieldConfig['upload_dir'];
        if (is_array($fieldConfig['upload_dir'])) {
            $uploadDir = $fieldConfig['upload_dir']['value'];
            if (
                array_key_exists('scope_info', $fieldConfig['upload_dir'])
                && $fieldConfig['upload_dir']['scope_info']
            ) {
                $uploadDir = $this->_appendScopeInfo($uploadDir);
            }

            if (array_key_exists('config', $fieldConfig['upload_dir'])) {
                $uploadDir = $this->_mediaDirectory->getRelativePath($uploadDir);
            }
        }

        return $uploadDir;
    }

    /**
     * Getter for allowed extensions of uploaded files
     *
     * @return string[]
     */
    public function getAllowedExtensions()
    {
        return ['jpg', 'jpeg', 'gif', 'png'];
    }

    /**
     * @return array
     */
    public function getValue()
    {
        return $this->getData('value') ?: [];
    }
}
