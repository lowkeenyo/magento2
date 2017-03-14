<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\CustomerImportExport\Test\Constraint;

use Magento\Mtf\Constraint\AbstractConstraint;
use Magento\ImportExport\Test\Fixture\ImportData;
use Magento\Mtf\Fixture\FixtureFactory;
use Magento\Customer\Test\Page\Adminhtml\CustomerIndexEdit;

/**
 * Assert addresses from csv import file and page are match.
 */
class AssertImportCustomerAddresses extends AbstractConstraint
{
    /**
     * Array keys mapping for csv file.
     *
     * @var array
     */
    private $mappingKeys = [
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'postcode' => 'postcode',
        'region' => 'region_id',
        'city' => 'city',
        'company' => 'company',
        'country_id' => 'country_id',
        'street' => 'street',
        'telephone' => 'telephone',
    ];

    /**
     * Array keys mapping for countries ids.
     *
     * @param array
     */
    private $mappingCountries = [
        'US' => 'United States'
    ];

    /**
     * Customer edit page on backend.
     *
     * @var CustomerIndexEdit
     */
    private $customerIndexEdit;

    /**
     * Fixture factory.
     *
     * @var FixtureFactory
     */
    private $fixtureFactory;

    /**
     * Import fixture.
     *
     * @var ImportData
     */
    private $import;

    /**
     * Assert imported customer addresses are correct.
     *
     * @param CustomerIndexEdit $customerIndexEdit
     * @param FixtureFactory $fixtureFactory
     * @param ImportData $import
     * @return void
     */
    public function processAssert(
        CustomerIndexEdit $customerIndexEdit,
        FixtureFactory $fixtureFactory,
        ImportData $import
    ) {
        $this->customerIndexEdit = $customerIndexEdit;
        $this->fixtureFactory = $fixtureFactory;
        $this->import = $import;

        $resultArrays = $this->getPrepareAddresses();

        \PHPUnit_Framework_Assert::assertEquals(
            $resultArrays['pageData'],
            $resultArrays['csvData'],
            'Addresses from page and csv are not match.'
        );
    }

    /**
     * Prepare arrays for compare.
     *
     * @return array
     */
    private function getPrepareAddresses()
    {
        $addressTemplate = ($this->import->getBehavior() !== 'Delete Entities')
            ? $this->fixtureFactory->createByCode('address', ['dataset' => 'US_address_1_without_email'])
            : null;
        $customers = $this->import->getDataFieldConfig('import_file')['source']->getEntities();
        $customerForm = $this->customerIndexEdit->getCustomerForm();

        // Prepare customer address data from page form.
        $resultAddressesArray = [];
        foreach ($customers as $customer) {
            $this->customerIndexEdit->open(['id' => $customer->getId()]);
            $customerForm->openTab('addresses');
            $address = $customerForm->getTab('addresses')->getDataAddresses($addressTemplate)[0];
            if (!empty($address)) {
                $resultAddressesArray[] = $address;
            }
        }

        // Prepare customer address data from csv file.
        $resultCsvArray = [];
        if ($this->import->getBehavior() !== 'Delete Entities') {
            $resultCsvArray = $this->getResultCsv();
        }
        return ['pageData' => $resultAddressesArray, 'csvData' => $resultCsvArray];
    }

    /**
     * Prepare array from csv file.
     *
     * @return array
     */
    private function getResultCsv()
    {
        $csvData = $this->import->getDataFieldConfig('import_file')['source']->getCsv();

        $csvKeys = [];
        foreach (array_shift($csvData) as $csvKey) {
            $csvKeys[] = isset($this->mappingKeys[$csvKey]) ? $this->mappingKeys[$csvKey] : $csvKey;
        }

        $resultCsvData = [];
        foreach ($csvData as $csvRowData) {
            $csvRowData = array_combine($csvKeys, $csvRowData);
            $csvRowData = $this->deleteDirtData($csvRowData);
            if (isset($this->mappingCountries[$csvRowData['country_id']])) {
                $csvRowData['country_id'] = $this->mappingCountries[$csvRowData['country_id']];
            };
            $resultCsvData[] = $csvRowData;
        }
        return $resultCsvData;
    }

    /**
     * Delete waste data from array.
     *
     * @param array $csvData
     * @return array
     */
    private function deleteDirtData(array $csvData)
    {
        $necessaryData = array_flip($this->mappingKeys);
        $wasteKeys = array_keys(array_diff_key($csvData, $necessaryData));
        foreach ($wasteKeys as $key) {
            unset($csvData[$key]);
        };
        return $csvData;
    }

    /**
     * Return string representation of object.
     *
     * @return string
     */
    public function toString()
    {
        return 'Imported advanced prices are correct.';
    }
}
