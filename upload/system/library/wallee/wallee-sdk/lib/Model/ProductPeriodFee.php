<?php
/**
 * wallee SDK
 *
 * This library allows to interact with the wallee payment service.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


namespace Wallee\Sdk\Model;

use \ArrayAccess;
use \Wallee\Sdk\ObjectSerializer;

/**
 * ProductPeriodFee model
 *
 * @category    Class
 * @description 
 * @package     Wallee\Sdk
 * @author      wallee AG
 * @license     http://www.apache.org/licenses/LICENSE-2.0 Apache License v2
 */
class ProductPeriodFee implements ModelInterface, ArrayAccess
{
    const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $swaggerModelName = 'ProductPeriodFee';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerTypes = [
        'component' => '\Wallee\Sdk\Model\SubscriptionProductComponent',
        'description' => '\Wallee\Sdk\Model\DatabaseTranslatedString',
        'id' => 'int',
        'ledger_entry_title' => '\Wallee\Sdk\Model\DatabaseTranslatedString',
        'linked_space_id' => 'int',
        'name' => '\Wallee\Sdk\Model\DatabaseTranslatedString',
        'number_of_free_trial_periods' => 'int',
        'period_fee' => '\Wallee\Sdk\Model\PersistableCurrencyAmount[]',
        'type' => '\Wallee\Sdk\Model\ProductFeeType',
        'version' => 'int'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerFormats = [
        'component' => null,
        'description' => null,
        'id' => 'int64',
        'ledger_entry_title' => null,
        'linked_space_id' => 'int64',
        'name' => null,
        'number_of_free_trial_periods' => 'int32',
        'period_fee' => null,
        'type' => null,
        'version' => 'int32'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'component' => 'component',
        'description' => 'description',
        'id' => 'id',
        'ledger_entry_title' => 'ledgerEntryTitle',
        'linked_space_id' => 'linkedSpaceId',
        'name' => 'name',
        'number_of_free_trial_periods' => 'numberOfFreeTrialPeriods',
        'period_fee' => 'periodFee',
        'type' => 'type',
        'version' => 'version'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'component' => 'setComponent',
        'description' => 'setDescription',
        'id' => 'setId',
        'ledger_entry_title' => 'setLedgerEntryTitle',
        'linked_space_id' => 'setLinkedSpaceId',
        'name' => 'setName',
        'number_of_free_trial_periods' => 'setNumberOfFreeTrialPeriods',
        'period_fee' => 'setPeriodFee',
        'type' => 'setType',
        'version' => 'setVersion'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'component' => 'getComponent',
        'description' => 'getDescription',
        'id' => 'getId',
        'ledger_entry_title' => 'getLedgerEntryTitle',
        'linked_space_id' => 'getLinkedSpaceId',
        'name' => 'getName',
        'number_of_free_trial_periods' => 'getNumberOfFreeTrialPeriods',
        'period_fee' => 'getPeriodFee',
        'type' => 'getType',
        'version' => 'getVersion'
    ];

    

    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        
        $this->container['component'] = isset($data['component']) ? $data['component'] : null;
        
        $this->container['description'] = isset($data['description']) ? $data['description'] : null;
        
        $this->container['id'] = isset($data['id']) ? $data['id'] : null;
        
        $this->container['ledger_entry_title'] = isset($data['ledger_entry_title']) ? $data['ledger_entry_title'] : null;
        
        $this->container['linked_space_id'] = isset($data['linked_space_id']) ? $data['linked_space_id'] : null;
        
        $this->container['name'] = isset($data['name']) ? $data['name'] : null;
        
        $this->container['number_of_free_trial_periods'] = isset($data['number_of_free_trial_periods']) ? $data['number_of_free_trial_periods'] : null;
        
        $this->container['period_fee'] = isset($data['period_fee']) ? $data['period_fee'] : null;
        
        $this->container['type'] = isset($data['type']) ? $data['type'] : null;
        
        $this->container['version'] = isset($data['version']) ? $data['version'] : null;
        
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        return $invalidProperties;
    }

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerTypes()
    {
        return self::$swaggerTypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerFormats()
    {
        return self::$swaggerFormats;
    }


    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$swaggerModelName;
    }

    

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {
        return count($this->listInvalidProperties()) === 0;
    }

    

    /**
     * Gets component
     *
     * @return \Wallee\Sdk\Model\SubscriptionProductComponent
     */
    public function getComponent()
    {
        return $this->container['component'];
    }

    /**
     * Sets component
     *
     * @param \Wallee\Sdk\Model\SubscriptionProductComponent $component 
     *
     * @return $this
     */
    public function setComponent($component)
    {
        $this->container['component'] = $component;

        return $this;
    }
    

    /**
     * Gets description
     *
     * @return \Wallee\Sdk\Model\DatabaseTranslatedString
     */
    public function getDescription()
    {
        return $this->container['description'];
    }

    /**
     * Sets description
     *
     * @param \Wallee\Sdk\Model\DatabaseTranslatedString $description The description of a component fee describes the fee to the subscriber. The description may be shown in documents or on certain user interfaces.
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->container['description'] = $description;

        return $this;
    }
    

    /**
     * Gets id
     *
     * @return int
     */
    public function getId()
    {
        return $this->container['id'];
    }

    /**
     * Sets id
     *
     * @param int $id The ID is the primary key of the entity. The ID identifies the entity uniquely.
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->container['id'] = $id;

        return $this;
    }
    

    /**
     * Gets ledger_entry_title
     *
     * @return \Wallee\Sdk\Model\DatabaseTranslatedString
     */
    public function getLedgerEntryTitle()
    {
        return $this->container['ledger_entry_title'];
    }

    /**
     * Sets ledger_entry_title
     *
     * @param \Wallee\Sdk\Model\DatabaseTranslatedString $ledger_entry_title The ledger entry title will be used for the title in the ledger entry and in the invoice.
     *
     * @return $this
     */
    public function setLedgerEntryTitle($ledger_entry_title)
    {
        $this->container['ledger_entry_title'] = $ledger_entry_title;

        return $this;
    }
    

    /**
     * Gets linked_space_id
     *
     * @return int
     */
    public function getLinkedSpaceId()
    {
        return $this->container['linked_space_id'];
    }

    /**
     * Sets linked_space_id
     *
     * @param int $linked_space_id The linked space id holds the ID of the space to which the entity belongs to.
     *
     * @return $this
     */
    public function setLinkedSpaceId($linked_space_id)
    {
        $this->container['linked_space_id'] = $linked_space_id;

        return $this;
    }
    

    /**
     * Gets name
     *
     * @return \Wallee\Sdk\Model\DatabaseTranslatedString
     */
    public function getName()
    {
        return $this->container['name'];
    }

    /**
     * Sets name
     *
     * @param \Wallee\Sdk\Model\DatabaseTranslatedString $name The name of the fee should describe for the subscriber in few words for what the fee is for.
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->container['name'] = $name;

        return $this;
    }
    

    /**
     * Gets number_of_free_trial_periods
     *
     * @return int
     */
    public function getNumberOfFreeTrialPeriods()
    {
        return $this->container['number_of_free_trial_periods'];
    }

    /**
     * Sets number_of_free_trial_periods
     *
     * @param int $number_of_free_trial_periods The number of free trial periods specify how many periods are free of charge at the begining of the subscription.
     *
     * @return $this
     */
    public function setNumberOfFreeTrialPeriods($number_of_free_trial_periods)
    {
        $this->container['number_of_free_trial_periods'] = $number_of_free_trial_periods;

        return $this;
    }
    

    /**
     * Gets period_fee
     *
     * @return \Wallee\Sdk\Model\PersistableCurrencyAmount[]
     */
    public function getPeriodFee()
    {
        return $this->container['period_fee'];
    }

    /**
     * Sets period_fee
     *
     * @param \Wallee\Sdk\Model\PersistableCurrencyAmount[] $period_fee The period fee is charged for every period of the subscription except for those periods which are trial periods.
     *
     * @return $this
     */
    public function setPeriodFee($period_fee)
    {
        $this->container['period_fee'] = $period_fee;

        return $this;
    }
    

    /**
     * Gets type
     *
     * @return \Wallee\Sdk\Model\ProductFeeType
     */
    public function getType()
    {
        return $this->container['type'];
    }

    /**
     * Sets type
     *
     * @param \Wallee\Sdk\Model\ProductFeeType $type 
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->container['type'] = $type;

        return $this;
    }
    

    /**
     * Gets version
     *
     * @return int
     */
    public function getVersion()
    {
        return $this->container['version'];
    }

    /**
     * Sets version
     *
     * @param int $version The version number indicates the version of the entity. The version is incremented whenever the entity is changed.
     *
     * @return $this
     */
    public function setVersion($version)
    {
        $this->container['version'] = $version;

        return $this;
    }
    
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Sets value based on offset.
     *
     * @param integer $offset Offset
     * @param mixed   $value  Value to be set
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        if (defined('JSON_PRETTY_PRINT')) { // use JSON pretty print
            return json_encode(
                ObjectSerializer::sanitizeForSerialization($this),
                JSON_PRETTY_PRINT
            );
        }

        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}


