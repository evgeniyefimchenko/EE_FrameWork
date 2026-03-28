<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;
use classes\system\Hook;
use classes\system\Logger;
use classes\system\OperationResult;
use classes\system\PropertyFieldContract;

/**
 * РњРѕРґРµР»СЊ СЂР°Р±РѕС‚С‹ СЃРѕ СЃРІРѕР№СЃС‚РІР°РјРё
 */
class ModelProperties {

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РІСЃРµ СЃРІРѕР№СЃС‚РІР° РЅР° РѕСЃРЅРѕРІРµ СЃС‚Р°С‚СѓСЃР° Рё СЏР·С‹РєРѕРІРѕРіРѕ РєРѕРґР°     
     * @param string $status РЎС‚Р°С‚СѓСЃ С‚РёРїР° СЃРІРѕР№СЃС‚РІР° Constants::ALL_STATUS
     * @param string $languageCode РЇР·С‹РєРѕРІРѕР№ РєРѕРґ РґР»СЏ С„РёР»СЊС‚СЂР°С†РёРё. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ РєРѕРЅСЃС‚Р°РЅС‚Р° ENV_DEF_LANG
     * @return array Р’РѕР·РІСЂР°С‰Р°РµС‚ РјР°СЃСЃРёРІ РІСЃРµС… СЃРІРѕР№СЃС‚РІ, СЃРѕРѕС‚РІРµС‚СЃС‚РІСѓСЋС‰РёС… Р·Р°РґР°РЅРЅС‹Рј РєСЂРёС‚РµСЂРёСЏРј
     */
    public function getAllProperties($status = Constants::ALL_STATUS, $languageCode = ENV_DEF_LANG, $short = true) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        if ($short) {
            $sql_properties = "SELECT * FROM ?n WHERE status IN (?a) AND language_code = ?s";
            $res = SafeMySQL::gi()->getAll($sql_properties, Constants::PROPERTIES_TABLE, $status, $languageCode);
            return $res;
        } else {
            $return = [];
            $sql_properties = "SELECT property_id FROM ?n WHERE status IN (?a) AND language_code = ?s";
            $res = SafeMySQL::gi()->getAll($sql_properties, Constants::PROPERTIES_TABLE, $status, $languageCode);
            foreach ($res as $item) {
                $data_prop = $this->getPropertyData($item['property_id'], $languageCode, $status);
                if (!$data_prop)
                    continue;
                $return[] = $data_prop;
            }
            return $return;
        }
        return false;
    }

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РґР°РЅРЅС‹Рµ СЃРІРѕР№СЃС‚РІ СЃ СѓС‡РµС‚РѕРј РїР°СЂР°РјРµС‚СЂРѕРІ СЃРѕСЂС‚РёСЂРѕРІРєРё, С„РёР»СЊС‚СЂР°С†РёРё Рё РїР°РіРёРЅР°С†РёРё
     * @param string $order РџР°СЂР°РјРµС‚СЂ РґР»СЏ СЃРѕСЂС‚РёСЂРѕРІРєРё СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 'property_id ASC')
     * @param string|null $where РЈСЃР»РѕРІРёРµ РґР»СЏ С„РёР»СЊС‚СЂР°С†РёРё СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ NULL)
     * @param int $start РќР°С‡Р°Р»СЊРЅС‹Р№ РёРЅРґРµРєСЃ РґР»СЏ РїР°РіРёРЅР°С†РёРё СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 0)
     * @param int $limit РњР°РєСЃРёРјР°Р»СЊРЅРѕРµ РєРѕР»РёС‡РµСЃС‚РІРѕ СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ РґР»СЏ РІРѕР·РІСЂР°С‚Р° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 100)
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @return array РњР°СЃСЃРёРІ, СЃРѕРґРµСЂР¶Р°С‰РёР№ РґР°РЅРЅС‹Рµ СЃРІРѕР№СЃС‚РІ Рё РѕР±С‰РµРµ РєРѕР»РёС‡РµСЃС‚РІРѕ СЃРІРѕР№СЃС‚РІ
     */
    public function getPropertiesData($order = 'property_id ASC', $where = null, $start = 0, $limit = 100, $languageCode = ENV_DEF_LANG) {
        $orderString = $order ? $order : 'property_id ASC';
        $whereString = $where ? $where . ' AND language_code = ?s' : 'language_code = ?s';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_properties = "SELECT property_id FROM ?n WHERE $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_properties = "SELECT property_id FROM ?n WHERE $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_properties, Constants::PROPERTIES_TABLE, $languageCode, $start, $limit);
        $res = [];
        foreach ($res_array as $property) {
            $data_prop = $this->getPropertyData($property['property_id'], $languageCode);
            if (!$data_prop)
                continue;
            $res[] = $data_prop;
        }
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n WHERE $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTIES_TABLE, $languageCode);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РґР°РЅРЅС‹Рµ РѕРґРЅРѕРіРѕ СЃРІРѕР№СЃС‚РІР° РїРѕ РµРіРѕ ID
     * @param int $propertyId ID СЃРІРѕР№СЃС‚РІР°, РґР»СЏ РєРѕС‚РѕСЂРѕРіРѕ РЅСѓР¶РЅРѕ РїРѕР»СѓС‡РёС‚СЊ РґР°РЅРЅС‹Рµ
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @param string $status РЎС‚Р°С‚СѓСЃ С‚РёРїР° СЃРІРѕР№СЃС‚РІР° Constants::ALL_STATUS
     * @return array|null РњР°СЃСЃРёРІ СЃ РґР°РЅРЅС‹РјРё СЃРІРѕР№СЃС‚РІР° РёР»Рё NULL, РµСЃР»Рё СЃРІРѕР№СЃС‚РІРѕ РЅРµ РЅР°Р№РґРµРЅРѕ
     */
    public function getPropertyData($propertyId, $languageCode = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        $sql_property = 'SELECT p.*, pt.name as type_name, pt.fields as fields 
            FROM ?n AS p 
            LEFT JOIN ?n AS pt ON p.type_id = pt.type_id 
            WHERE pt.status IN (?a) AND p.property_id = ?i AND p.language_code = ?s';
        $propertyData = SafeMySQL::gi()->getRow(
                $sql_property,
                Constants::PROPERTIES_TABLE,
                Constants::PROPERTY_TYPES_TABLE,
                $status,
                $propertyId,
                $languageCode
        );
        if (!$propertyData) {
            return null;
        }
        return $propertyData;
    }

    /**
     * Р’РµСЂРЅС‘С‚ type_id СЃРІРѕР№СЃС‚РІР° РїРѕ РµРіРѕ property_id
     * @param int $propertyId
     * @return int|null
     */
    public function getTypeIdByPropertyId(int $propertyId): ?int {
        return SafeMySQL::gi()->getOne('SELECT type_id FROM ?n WHERE property_id = ?i', Constants::PROPERTIES_TABLE, $propertyId);
    }

    /**
     * РћР±РЅРѕРІР»СЏРµС‚ РґР°РЅРЅС‹Рµ СЃРІРѕР№СЃС‚РІР° РІ С‚Р°Р±Р»РёС†Рµ СЃРІРѕР№СЃС‚РІ
     * @param array $propertyData РђСЃСЃРѕС†РёР°С‚РёРІРЅС‹Р№ РјР°СЃСЃРёРІ СЃ РґР°РЅРЅС‹РјРё СЃРІРѕР№СЃС‚РІР° РґР»СЏ РѕР±РЅРѕРІР»РµРЅРёСЏ
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @return int|bool ID РѕР±РЅРѕРІР»РµРЅРЅРѕРіРѕ СЃРІРѕР№СЃС‚РІР° РёР»Рё false РІ СЃР»СѓС‡Р°Рµ РЅРµСѓРґР°С‡Рё
     */
    public function updatePropertyData(array $propertyData = [], string $languageCode = ENV_DEF_LANG): OperationResult {
        $propertyData = SafeMySQL::gi()->filterArray($propertyData, SysClass::ee_getFieldsTable(Constants::PROPERTIES_TABLE));
        if (is_array($propertyData['default_values'])) {
            $propertyData['default_values'] = json_encode($propertyData['default_values'], JSON_UNESCAPED_UNICODE);
        } elseif (!SysClass::ee_isValidJson($propertyData['default_values'])) {
            $propertyData['default_values'] = '[]';
        }
        $propertyData = array_map('trim', $propertyData);
        $propertyData['is_multiple'] = isset($propertyData['is_multiple']) && ($propertyData['is_multiple'] == 'on' || $propertyData['is_multiple'] == 1) ? 1 : 0;
        $propertyData['is_required'] = isset($propertyData['is_required']) && ($propertyData['is_required'] == 'on' || $propertyData['is_required'] == 1) ? 1 : 0;
        $propertyData['language_code'] = $languageCode;  // РґРѕР±Р°РІР»РµРЅРѕ
        if (empty($propertyData['name']) || !isset($propertyData['type_id'])) {
            $message = 'Error: property_data name or type_id';
            Logger::error('property', $message, ['property_data' => $propertyData], [
                'initiator' => __FUNCTION__,
                'details' => $message,
            ]);
            return OperationResult::validation('Не указаны обязательные поля свойства: name или type_id', $propertyData);
        }
        if (!empty($propertyData['property_id'])) {
            $propertyId = $propertyData['property_id'];
            unset($propertyData['property_id']); // РЈРґР°Р»СЏРµРј property_id РёР· РјР°СЃСЃРёРІР° РґР°РЅРЅС‹С…, С‡С‚РѕР±С‹ РёР·Р±РµР¶Р°С‚СЊ РµРіРѕ РѕР±РЅРѕРІР»РµРЅРёРµ
            $sql = "UPDATE ?n SET ?u WHERE property_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTIES_TABLE, $propertyData, $propertyId);
            if (!$result) {
                Logger::error('property', 'Ошибка обновления свойства', ['property_data' => $propertyData, 'query' => SafeMySQL::gi()->lastQuery()], [
                    'initiator' => __FUNCTION__,
                ]);
                return OperationResult::failure('Ошибка обновления свойства', 'property_update_error', ['property_data' => $propertyData]);
            }
            return OperationResult::success((int) $propertyId, '', 'updated');
        } else {
            unset($propertyData['property_id']);
        }
        // РџСЂРѕРІРµСЂСЏРµРј СѓРЅРёРєР°Р»СЊРЅРѕСЃС‚СЊ РёРјРµРЅРё РІ СЂР°РјРєР°С… РѕРґРЅРѕРіРѕ С‚РёРїР° Рё СЏР·С‹РєР°
        $existingProperty = SafeMySQL::gi()->getRow(
            "SELECT property_id FROM ?n WHERE name = ?s AND type_id = ?i AND language_code = ?s",
            Constants::PROPERTIES_TABLE,
            $propertyData['name'], $propertyData['type_id'], $languageCode
        );
        if ($existingProperty) {
            $message = 'Error: existingProperty';
            Logger::warning('property', $message, ['property_data' => $propertyData], [
                'initiator' => __FUNCTION__,
                'details' => $message,
            ]);
            return OperationResult::failure('Свойство с таким именем уже существует в рамках типа и языка', 'duplicate_property', ['property_data' => $propertyData]);
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTIES_TABLE, $propertyData);
        if (!$result) {
            Logger::error('property', 'Ошибка создания свойства', ['property_data' => $propertyData, 'query' => SafeMySQL::gi()->lastQuery()], [
                'initiator' => __FUNCTION__,
            ]);
            return OperationResult::failure('Ошибка создания свойства', 'property_insert_error', ['property_data' => $propertyData]);
        }
        return OperationResult::success((int) SafeMySQL::gi()->insertId(), '', 'created');
    }

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РІСЃРµ С‚РёРїС‹ СЃРІРѕР№СЃС‚РІ РёР· С‚Р°Р±Р»РёС†С‹ С‚РёРїРѕРІ СЃРІРѕР№СЃС‚РІ
     * @param string $status РЎС‚Р°С‚СѓСЃ С‚РёРїР° СЃРІРѕР№СЃС‚РІР° Constants::ALL_STATUS
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @return array РњР°СЃСЃРёРІ, СЃРѕРґРµСЂР¶Р°С‰РёР№ РІСЃРµ С‚РёРїС‹ СЃРІРѕР№СЃС‚РІ, РєР°Р¶РґС‹Р№ РёР· РєРѕС‚РѕСЂС‹С… РїСЂРµРґСЃС‚Р°РІР»РµРЅ Р°СЃСЃРѕС†РёР°С‚РёРІРЅС‹Рј РјР°СЃСЃРёРІРѕРј СЃ РґР°РЅРЅС‹РјРё С‚РёРїР° СЃРІРѕР№СЃС‚РІР°
     */
    public function getAllPropertyTypes($status = Constants::ALL_STATUS, $languageCode = ENV_DEF_LANG) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        $sql = "SELECT * FROM ?n WHERE status IN (?a) AND language_code = ?s";
        $property_types = SafeMySQL::gi()->getInd('type_id', $sql, Constants::PROPERTY_TYPES_TABLE, $status, $languageCode);
        return $property_types;
    }

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РґР°РЅРЅС‹Рµ С‚РёРїРѕРІ СЃРІРѕР№СЃС‚РІ СЃ СѓС‡РµС‚РѕРј РїР°СЂР°РјРµС‚СЂРѕРІ СЃРѕСЂС‚РёСЂРѕРІРєРё, С„РёР»СЊС‚СЂР°С†РёРё Рё РїР°РіРёРЅР°С†РёРё
     * @param string $order РџР°СЂР°РјРµС‚СЂ РґР»СЏ СЃРѕСЂС‚РёСЂРѕРІРєРё СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 'type_id ASC')
     * @param string|null $where РЈСЃР»РѕРІРёРµ РґР»СЏ С„РёР»СЊС‚СЂР°С†РёРё СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ NULL)
     * @param int $start РќР°С‡Р°Р»СЊРЅС‹Р№ РёРЅРґРµРєСЃ РґР»СЏ РїР°РіРёРЅР°С†РёРё СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 0)
     * @param int $limit РњР°РєСЃРёРјР°Р»СЊРЅРѕРµ РєРѕР»РёС‡РµСЃС‚РІРѕ СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ РґР»СЏ РІРѕР·РІСЂР°С‚Р° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 100)
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @return array РњР°СЃСЃРёРІ, СЃРѕРґРµСЂР¶Р°С‰РёР№ РґР°РЅРЅС‹Рµ С‚РёРїРѕРІ СЃРІРѕР№СЃС‚РІ Рё РѕР±С‰РµРµ РєРѕР»РёС‡РµСЃС‚РІРѕ С‚РёРїРѕРІ СЃРІРѕР№СЃС‚РІ
     */
    public function getTypePropertiesData(string $order = 'type_id ASC', $where = null, int $start = 0, int $limit = 100, string $languageCode = ENV_DEF_LANG) {
        $orderString = $order ? $order : 'type_id ASC';
        $whereString = $where ? $where . ' AND language_code = ?s' : 'WHERE language_code = ?s';
        if ($orderString) {
            $sql_types = "SELECT type_id FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_types = "SELECT type_id FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_types, Constants::PROPERTY_TYPES_TABLE, $languageCode, $start, $limit);
        $res = [];
        foreach ($res_array as $type) {
            $res[] = $this->getTypePropertyData($type['type_id'], $languageCode);
        }
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTY_TYPES_TABLE, $languageCode);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РґР°РЅРЅС‹Рµ РѕРґРЅРѕРіРѕ С‚РёРїР° СЃРІРѕР№СЃС‚РІР° РїРѕ РµРіРѕ ID
     * @param int $typeId ID С‚РёРїР° СЃРІРѕР№СЃС‚РІР°, РґР»СЏ РєРѕС‚РѕСЂРѕРіРѕ РЅСѓР¶РЅРѕ РїРѕР»СѓС‡РёС‚СЊ РґР°РЅРЅС‹Рµ
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @return array|null РњР°СЃСЃРёРІ СЃ РґР°РЅРЅС‹РјРё С‚РёРїР° СЃРІРѕР№СЃС‚РІР° РёР»Рё NULL, РµСЃР»Рё С‚РёРї СЃРІРѕР№СЃС‚РІР° РЅРµ РЅР°Р№РґРµРЅ
     */
    public function getTypePropertyData(int $typeId, string $languageCode = ENV_DEF_LANG) {
        $sql = "SELECT * FROM ?n WHERE type_id = ?i AND language_code = ?s";
        $typeData = SafeMySQL::gi()->getRow($sql, Constants::PROPERTY_TYPES_TABLE, $typeId, $languageCode);
        if (!$typeData) {
            return null;
        }
        return $typeData;
    }

    /**
     * РСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р»Рё С‚РёРї СЃРІРѕР№СЃС‚РІР° Сѓ Р»СЋР±РѕРіРѕ СЃРІРѕР№СЃС‚РІР°
     * @param int $typeId
     * @return bool
     */
    public function isExistPropertiesWithType(int $typeId): bool {
        $sql = 'SELECT property_id FROM ?n WHERE type_id = ?i';
        $result = SafeMySQL::gi()->getOne($sql, Constants::PROPERTIES_TABLE, $typeId) ? true : false;
        return $result;
    }

    /**
     * РСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р»Рё СЃРІРѕР№СЃС‚РІРѕ РІ РєР°РєРѕРј С‚Рѕ РЅР°Р±РѕСЂРµ СЃРІРѕР№СЃС‚РІ
     * @param int $propertyId
     * @return bool
     */
    public function isExistSetsWithProperty(int $propertyId): bool {
        $sql = 'SELECT set_id FROM ?n WHERE property_id = ?i';
        $result = SafeMySQL::gi()->getOne($sql, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $propertyId) ? true : false;
        return $result;
    }

    /**
     * РСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р»Рё РЅР°Р±РѕСЂ СЃРІРѕР№СЃС‚РІ РІ Р»СЋР±РѕРј С‚РёРїРµ РєР°С‚РµРіРѕСЂРёР№
     * @param int $setId
     * @return bool
     */
    public function isExistCategoryTypeWithSet(int $setId): bool {
        $sql = 'SELECT type_id FROM ?n WHERE set_id = ?i';
        $result = SafeMySQL::gi()->getOne($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $setId) ? true : false;
        return $result;
    }

    /**
     * РћР±РЅРѕРІР»СЏРµС‚ РґР°РЅРЅС‹Рµ С‚РёРїР° СЃРІРѕР№СЃС‚РІР° РІ С‚Р°Р±Р»РёС†Рµ С‚РёРїРѕРІ СЃРІРѕР№СЃС‚РІ
     * @param array $propertyTypeData РђСЃСЃРѕС†РёР°С‚РёРІРЅС‹Р№ РјР°СЃСЃРёРІ СЃ РґР°РЅРЅС‹РјРё С‚РёРїР° СЃРІРѕР№СЃС‚РІР° РґР»СЏ РѕР±РЅРѕРІР»РµРЅРёСЏ
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @return int|bool ID РѕР±РЅРѕРІР»РµРЅРЅРѕРіРѕ С‚РёРїР° СЃРІРѕР№СЃС‚РІР° РёР»Рё false РІ СЃР»СѓС‡Р°Рµ РЅРµСѓРґР°С‡Рё
     */
    public function updatePropertyTypeData(array $propertyTypeData = [], string $languageCode = ENV_DEF_LANG): OperationResult {
        $propertyTypeData = SafeMySQL::gi()->filterArray($propertyTypeData, SysClass::ee_getFieldsTable(Constants::PROPERTY_TYPES_TABLE));
        $propertyTypeData = array_map('trim', $propertyTypeData);
        $propertyTypeData['language_code'] = $languageCode;  // РґРѕР±Р°РІР»РµРЅРѕ
        if (empty($propertyTypeData['name'])) {
            return OperationResult::validation('Не указано имя типа свойства', $propertyTypeData);
        }
        if (!empty($propertyTypeData['type_id']) && $propertyTypeData['type_id'] != 0) {
            $typeId = $propertyTypeData['type_id'];
            unset($propertyTypeData['type_id']); // РЈРґР°Р»СЏРµРј type_id РёР· РјР°СЃСЃРёРІР° РґР°РЅРЅС‹С…, С‡С‚РѕР±С‹ РёР·Р±РµР¶Р°С‚СЊ РµРіРѕ РѕР±РЅРѕРІР»РµРЅРёРµ
            $sql = "UPDATE ?n SET ?u WHERE type_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_TYPES_TABLE, $propertyTypeData, $typeId);
            if (!$result) {
                Logger::error('property_type', 'Ошибка обновления типа свойства', ['property_type_data' => $propertyTypeData, 'query' => SafeMySQL::gi()->lastQuery()], [
                    'initiator' => __FUNCTION__,
                ]);
                return OperationResult::failure('Ошибка обновления типа свойства', 'property_type_update_error', ['property_type_data' => $propertyTypeData]);
            }
            return OperationResult::success((int) $typeId, '', 'updated');
        } else {
            unset($propertyTypeData['type_id']);
        }
        // РџСЂРѕРІРµСЂСЏРµРј СѓРЅРёРєР°Р»СЊРЅРѕСЃС‚СЊ РёРјРµРЅРё РІ СЂР°РјРєР°С… РѕРґРЅРѕРіРѕ СЏР·С‹РєР°
        $existingType = SafeMySQL::gi()->getRow(
                "SELECT type_id FROM ?n WHERE name = ?s AND language_code = ?s",
                Constants::PROPERTY_TYPES_TABLE,
                $propertyTypeData['name'], $languageCode
        );
        if ($existingType) {
            return OperationResult::failure('Тип свойства с таким именем уже существует для данного языка', 'duplicate_property_type', ['property_type_data' => $propertyTypeData]);
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_TYPES_TABLE, $propertyTypeData);
        if (!$result) {
            Logger::error('property_type', 'Ошибка создания типа свойства', ['property_type_data' => $propertyTypeData, 'query' => SafeMySQL::gi()->lastQuery()], [
                'initiator' => __FUNCTION__,
            ]);
            return OperationResult::failure('Ошибка создания типа свойства', 'property_type_insert_error', ['property_type_data' => $propertyTypeData]);
        }
        return OperationResult::success((int) SafeMySQL::gi()->insertId(), '', 'created');
    }

    /**
     * РЈРґР°Р»РёС‚ С‚РёРї СЃРІРѕР№СЃС‚РІР°
     * @param type $typeId
     */
    public function typePropertiesDelete(int $typeId) {
        try {
            $sql = 'SELECT 1 FROM ?n WHERE type_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::PROPERTIES_TABLE, $typeId)) {
                return OperationResult::failure('Нельзя удалить тип свойства, так как он используется!', 'property_type_delete_blocked', ['type_id' => $typeId]);
            }
            $sql_delete = "DELETE FROM ?n WHERE type_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::PROPERTY_TYPES_TABLE, $typeId);
            return $result
                ? OperationResult::success(['type_id' => $typeId], '', 'deleted')
                : OperationResult::failure('Ошибка при выполнении запроса DELETE', 'property_type_delete_error', ['type_id' => $typeId]);
        } catch (Exception $e) {
            Logger::error('property_type', $e->getMessage(), ['type_id' => $typeId, 'exception' => $e], [
                'initiator' => __FUNCTION__,
                'include_trace' => true,
            ]);
            return OperationResult::failure($e->getMessage(), 'property_type_delete_exception', ['type_id' => $typeId]);
        }
    }

    /**
     * РЈРґР°Р»РёС‚ СЃРІРѕР№СЃС‚РІРѕ
     * @param type $propertyId
     */
    public function propertyDelete(int $propertyId) {
        try {
            $sql = 'SELECT 1 FROM ?n WHERE property_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::PROPERTY_VALUES_TABLE, $propertyId)) {
                return OperationResult::failure('Нельзя удалить свойство, так как оно используется!', 'property_delete_blocked', ['property_id' => $propertyId]);
            }
            $sql_delete = "DELETE FROM ?n WHERE property_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::PROPERTIES_TABLE, $propertyId);
            return $result
                ? OperationResult::success(['property_id' => $propertyId], '', 'deleted')
                : OperationResult::failure('Ошибка при выполнении запроса DELETE', 'property_delete_error', ['property_id' => $propertyId]);
        } catch (Exception $e) {
            Logger::error('property', $e->getMessage(), ['property_id' => $propertyId, 'exception' => $e], [
                'initiator' => __FUNCTION__,
                'include_trace' => true,
            ]);
            return OperationResult::failure($e->getMessage(), 'property_delete_exception', ['property_id' => $propertyId]);
        }
    }

    /**
     * Р’РµСЂРЅС‘С‚ РґР°РЅРЅС‹Рµ РІСЃРµС… РЅР°Р±РѕСЂРѕРІ СЃРІРѕР№СЃС‚РІ
     * @param bool $short - РІРµСЂРЅС‘С‚ С‚РѕР»СЊРєРѕ set_id
     * @param array $select - РјР°СЃСЃРёРІ РїРѕР»РµР№ Р‘Р”
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РІ С„РѕСЂРјР°С‚Рµ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ ENV_DEF_LANG
     * @return array|bool
     */
    public function getAllPropertySetsData(bool $short = true, array $select = [], string $languageCode = ENV_DEF_LANG): array|bool {
        if ($short) {
            return SafeMySQL::gi()->getInd('set_id', 'SELECT set_id FROM ?n WHERE language_code = ?s', Constants::PROPERTY_SETS_TABLE, $languageCode);
        } else {
            if (!count($select)) {
                $select = ['*'];
            }
            return SafeMySQL::gi()->getInd('set_id', 'SELECT ' . implode(',', $select) . ' FROM ?n WHERE language_code = ?s', Constants::PROPERTY_SETS_TABLE, $languageCode);
        }
    }

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РґР°РЅРЅС‹Рµ РЅР°Р±РѕСЂРѕРІ СЃРІРѕР№СЃС‚РІ СЃ СѓС‡РµС‚РѕРј РїР°СЂР°РјРµС‚СЂРѕРІ СЃРѕСЂС‚РёСЂРѕРІРєРё, С„РёР»СЊС‚СЂР°С†РёРё Рё РїР°РіРёРЅР°С†РёРё
     * @param string $order РџР°СЂР°РјРµС‚СЂ РґР»СЏ СЃРѕСЂС‚РёСЂРѕРІРєРё СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 'set_id ASC')
     * @param string|null $where РЈСЃР»РѕРІРёРµ РґР»СЏ С„РёР»СЊС‚СЂР°С†РёРё СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ NULL)
     * @param int $start РќР°С‡Р°Р»СЊРЅС‹Р№ РёРЅРґРµРєСЃ РґР»СЏ РїР°РіРёРЅР°С†РёРё СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 0)
     * @param int $limit РњР°РєСЃРёРјР°Р»СЊРЅРѕРµ РєРѕР»РёС‡РµСЃС‚РІРѕ СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ РґР»СЏ РІРѕР·РІСЂР°С‚Р° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 100)
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РІ С„РѕСЂРјР°С‚Рµ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ ENV_DEF_LANG
     * @return array РњР°СЃСЃРёРІ, СЃРѕРґРµСЂР¶Р°С‰РёР№ РґР°РЅРЅС‹Рµ РЅР°Р±РѕСЂРѕРІ СЃРІРѕР№СЃС‚РІ Рё РѕР±С‰РµРµ РєРѕР»РёС‡РµСЃС‚РІРѕ РЅР°Р±РѕСЂРѕРІ СЃРІРѕР№СЃС‚РІ
     */
    public function getPropertySetsData(bool|string $order = 'set_id ASC', bool|string $where = false, int $start = 0, int $limit = 100, string $languageCode = ENV_DEF_LANG): array {
        $orderString = $order ? $order : 'set_id ASC';
        $whereString = $where ? "WHERE " . $where . " AND language_code = ?s" : "WHERE language_code = ?s";
        // Р—Р°РїСЂРѕСЃ РґР»СЏ РїРѕР»СѓС‡РµРЅРёСЏ РёРґРµРЅС‚РёС„РёРєР°С‚РѕСЂРѕРІ РЅР°Р±РѕСЂРѕРІ СЃРІРѕР№СЃС‚РІ
        $sql_sets = "SELECT set_id FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        $res_array = SafeMySQL::gi()->getAll($sql_sets, Constants::PROPERTY_SETS_TABLE, $languageCode, $start, $limit);
        $res = [];
        // РџРѕР»СѓС‡Р°РµРј РґР°РЅРЅС‹Рµ РєР°Р¶РґРѕРіРѕ РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ
        foreach ($res_array as $set) {
            $data_set = $this->getPropertySetData($set['set_id'], $languageCode);
            if (!$data_set) {
                continue;
            }
            $res[$set['set_id']] = $data_set;
        }
        // Р—Р°РїСЂРѕСЃ РґР»СЏ РїРѕР»СѓС‡РµРЅРёСЏ РѕР±С‰РµРіРѕ РєРѕР»РёС‡РµСЃС‚РІР° РЅР°Р±РѕСЂРѕРІ СЃРІРѕР№СЃС‚РІ
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTY_SETS_TABLE, $languageCode);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РґР°РЅРЅС‹Рµ РѕРґРЅРѕРіРѕ РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ РїРѕ РµРіРѕ ID
     * @param int $setId ID РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ, РґР»СЏ РєРѕС‚РѕСЂРѕРіРѕ РЅСѓР¶РЅРѕ РїРѕР»СѓС‡РёС‚СЊ РґР°РЅРЅС‹Рµ
     * @return array|null РњР°СЃСЃРёРІ СЃ РґР°РЅРЅС‹РјРё РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ РёР»Рё NULL, РµСЃР»Рё РЅР°Р±РѕСЂ СЃРІРѕР№СЃС‚РІ РЅРµ РЅР°Р№РґРµРЅ
     */
    public function getPropertySetData(int $setId, string $languageCode = ENV_DEF_LANG): bool|array {
        // РџРѕР»СѓС‡Р°РµРј РѕСЃРЅРѕРІРЅС‹Рµ РґР°РЅРЅС‹Рµ РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ
        $sql_set = 'SELECT * FROM ?n WHERE set_id = ?i';
        $setData = SafeMySQL::gi()->getRow($sql_set, Constants::PROPERTY_SETS_TABLE, $setId);
        if (!$setData) {
            return false;
        }
        // РџРѕР»СѓС‡Р°РµРј СЃРІРѕР№СЃС‚РІР°, СЃРІСЏР·Р°РЅРЅС‹Рµ СЃ СЌС‚РёРј РЅР°Р±РѕСЂРѕРј
        $sql_properties = '
        SELECT p.property_id as property_id, p.name, p.default_values, p.is_multiple, p.is_required, p.sort, p.entity_type as property_entity_type, pt.fields AS type_fields
        FROM ?n p
        JOIN ?n ps2p ON p.property_id = ps2p.property_id
        LEFT JOIN ?n pt ON pt.type_id = p.type_id
        WHERE ps2p.set_id = ?i';
        $properties = SafeMySQL::gi()->getInd('property_id', $sql_properties, Constants::PROPERTIES_TABLE, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, Constants::PROPERTY_TYPES_TABLE, $setId);
        // Р”РѕР±Р°РІР»СЏРµРј СЃРІРѕР№СЃС‚РІР° Рє РґР°РЅРЅС‹Рј РЅР°Р±РѕСЂР°
        $setData['properties'] = $properties;
        return $setData;
    }

    /**
     * РћР±РЅРѕРІР»СЏРµС‚ РґР°РЅРЅС‹Рµ РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ РІ С‚Р°Р±Р»РёС†Рµ РЅР°Р±РѕСЂРѕРІ СЃРІРѕР№СЃС‚РІ
     * @param array $propertySetData РђСЃСЃРѕС†РёР°С‚РёРІРЅС‹Р№ РјР°СЃСЃРёРІ СЃ РґР°РЅРЅС‹РјРё РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ РґР»СЏ РѕР±РЅРѕРІР»РµРЅРёСЏ
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РІ С„РѕСЂРјР°С‚Рµ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ ENV_DEF_LANG
     * @return int|bool ID РѕР±РЅРѕРІР»РµРЅРЅРѕРіРѕ РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ РёР»Рё false РІ СЃР»СѓС‡Р°Рµ РЅРµСѓРґР°С‡Рё
     */
    public function updatePropertySetData(array $propertySetData = [], string $languageCode = ENV_DEF_LANG): OperationResult {        
        // Р¤РёР»СЊС‚СЂСѓРµРј РґР°РЅРЅС‹Рµ РїРѕ РїРѕР»СЏРј С‚Р°Р±Р»РёС†С‹ РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ
        $propertySetData = SafeMySQL::gi()->filterArray($propertySetData, SysClass::ee_getFieldsTable(Constants::PROPERTY_SETS_TABLE));
        $propertySetData = array_map('trim', $propertySetData);
        // РџСЂРѕРІРµСЂРєР° РѕР±СЏР·Р°С‚РµР»СЊРЅРѕРіРѕ РїРѕР»СЏ 'name'
        if (empty($propertySetData['name'])) {
            return OperationResult::validation('Не указано имя набора свойств', $propertySetData);
        }
        // Р”РѕР±Р°РІР»СЏРµРј СЏР·С‹Рє РІ РјР°СЃСЃРёРІ РґР°РЅРЅС‹С…
        $propertySetData['language_code'] = $languageCode;
        // Р•СЃР»Рё РµСЃС‚СЊ set_id, РѕР±РЅРѕРІР»СЏРµРј СЃСѓС‰РµСЃС‚РІСѓСЋС‰СѓСЋ Р·Р°РїРёСЃСЊ
        if (!empty($propertySetData['set_id']) && $propertySetData['set_id'] != 0) {
            $setId = $propertySetData['set_id'];
            unset($propertySetData['set_id']); // РЈРґР°Р»СЏРµРј set_id, С‡С‚РѕР±С‹ РёР·Р±РµР¶Р°С‚СЊ РµРіРѕ РѕР±РЅРѕРІР»РµРЅРёСЏ
            $sql = "UPDATE ?n SET ?u WHERE set_id = ?i AND language_code = ?s";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_SETS_TABLE, $propertySetData, $setId, $languageCode);
            if (!$result) {
                Logger::error('property_set', 'Ошибка обновления набора свойств', ['property_set_data' => $propertySetData, 'query' => SafeMySQL::gi()->lastQuery()], [
                    'initiator' => __FUNCTION__,
                ]);
                return OperationResult::failure('Ошибка обновления набора свойств', 'property_set_update_error', ['property_set_data' => $propertySetData]);
            }
            return OperationResult::success((int) $setId, '', 'updated');
        } else {
            unset($propertySetData['set_id']);
        }
        // РџСЂРѕРІРµСЂСЏРµРј СѓРЅРёРєР°Р»СЊРЅРѕСЃС‚СЊ РёРјРµРЅРё РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ РґР»СЏ СѓРєР°Р·Р°РЅРЅРѕРіРѕ СЏР·С‹РєР°
        $existingSet = SafeMySQL::gi()->getRow(
                "SELECT set_id FROM ?n WHERE name = ?s AND language_code = ?s",
                Constants::PROPERTY_SETS_TABLE,
                $propertySetData['name'],
                $languageCode
        );
        // Р•СЃР»Рё С‚Р°РєРѕР№ РЅР°Р±РѕСЂ СѓР¶Рµ СЃСѓС‰РµСЃС‚РІСѓРµС‚, РІРѕР·РІСЂР°С‰Р°РµРј false
        if ($existingSet) {
            return OperationResult::failure('Набор уже существует, измените имя!', 'duplicate_property_set', ['existing_set' => $existingSet]);
        }
        // Р’СЃС‚Р°РІР»СЏРµРј РЅРѕРІСѓСЋ Р·Р°РїРёСЃСЊ
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_SETS_TABLE, $propertySetData);
        if (!$result) {
            Logger::error('property_set', 'Ошибка создания набора свойств', ['property_set_data' => $propertySetData, 'query' => SafeMySQL::gi()->lastQuery()], [
                'initiator' => __FUNCTION__,
            ]);
            return OperationResult::failure('Ошибка создания набора свойств', 'property_set_insert_error', ['property_set_data' => $propertySetData]);
        }
        return OperationResult::success((int) SafeMySQL::gi()->insertId(), '', 'created');
    }

    /**
     * РЈРґР°Р»РёС‚ РЅР°Р±РѕСЂ СЃРІРѕР№СЃС‚РІ
     * @param int $setId - ID РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ
     * @return array - РњР°СЃСЃРёРІ СЃ СЂРµР·СѓР»СЊС‚Р°С‚РѕРј РёР»Рё РѕС€РёР±РєРѕР№
     */
    public function propertySetDelete($setId) {
        try {
            // РџСЂРѕРІРµСЂСЏРµРј, РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р»Рё РґР°РЅРЅС‹Р№ РЅР°Р±РѕСЂ СЃРІРѕР№СЃС‚РІ РІ СЃРІСЏР·Рё СЃ С‚РёРїР°РјРё РєР°С‚РµРіРѕСЂРёР№
            $sql = 'SELECT 1 FROM ?n WHERE set_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $setId)) {
                return OperationResult::failure('Нельзя удалить набор свойств, так как он связан с типами категорий!', 'property_set_delete_blocked', ['set_id' => $setId]);
            }
            // РџСЂРѕРІРµСЂСЏРµРј, РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р»Рё РґР°РЅРЅС‹Р№ РЅР°Р±РѕСЂ СЃРІРѕР№СЃС‚РІ РІ СЃРІСЏР·Рё СЃРѕ СЃРІРѕР№СЃС‚РІР°РјРё
            $sql = 'SELECT 1 FROM ?n WHERE set_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $setId)) {
                return OperationResult::failure('Нельзя удалить набор свойств, так как он связан со свойствами!', 'property_set_delete_blocked', ['set_id' => $setId]);
            }
            // РџСЂРѕРІРµСЂСЏРµРј, РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р»Рё РґР°РЅРЅС‹Р№ РЅР°Р±РѕСЂ СЃРІРѕР№СЃС‚РІ РІ СЃРІСЏР·Рё СЃ С‚РёРїР°РјРё РєР°С‚РµРіРѕСЂРёР№
            $sql = 'SELECT 1 FROM ?n WHERE set_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $setId)) {
                return OperationResult::failure('Нельзя удалить набор свойств, так как он связан с категориями!', 'property_set_delete_blocked', ['set_id' => $setId]);
            }
            // Р•СЃР»Рё РїСЂРѕРІРµСЂРєРё РїСЂРѕС€Р»Рё СѓСЃРїРµС€РЅРѕ, СѓРґР°Р»СЏРµРј РЅР°Р±РѕСЂ СЃРІРѕР№СЃС‚РІ
            $sql_delete = "DELETE FROM ?n WHERE set_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::PROPERTY_SETS_TABLE, $setId);
            return $result
                ? OperationResult::success(['set_id' => $setId], '', 'deleted')
                : OperationResult::failure('Ошибка при выполнении запроса DELETE', 'property_set_delete_error', ['set_id' => $setId]);
        } catch (Exception $e) {
            Logger::error('property_set', $e->getMessage(), ['set_id' => $setId, 'exception' => $e], [
                'initiator' => __FUNCTION__,
                'include_trace' => true,
            ]);
            return OperationResult::failure($e->getMessage(), 'property_set_delete_exception', ['set_id' => $setId]);
        }
    }

    /**
     * РЈРґР°Р»РёС‚СЊ РІСЃРµ СЃРІРѕР№СЃС‚РІР° РґР»СЏ СѓРєР°Р·Р°РЅРЅРѕРіРѕ РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ
     * @param int $setId ID РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ
     */
    public function deletePreviousProperties(int $setId): void {
        $delete_previous_properties = "DELETE FROM ?n WHERE set_id = ?i";
        SafeMySQL::gi()->query($delete_previous_properties, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $setId);
    }

    /**
     * РЈРґР°Р»РёС‚ Р·РЅР°С‡РµРЅРёРµ СЃРІРѕР№СЃС‚РІ РґР»СЏ СЃСѓС‰РЅРѕСЃС‚Рё
     * @param int $valueId
     */
    public function deletePropertyValues(int|array $valueId) {
        if (!is_array($valueId)) {
            $valueId = [$valueId];
        }
        $sql = 'DELETE FROM ?n WHERE value_id IN (?a)';
        SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $valueId);
    }

    /**
     * Р”РѕР±Р°РІРёС‚СЊ РІС‹Р±СЂР°РЅРЅС‹Рµ СЃРІРѕР№СЃС‚РІР° РІ С‚Р°Р±Р»РёС†Сѓ СЃРІСЏР·РµР№ РЅР°Р±РѕСЂРѕРІ СЃРІРѕР№СЃС‚РІ Рё СЃРІРѕР№СЃС‚РІ
     * @param int   $setId ID РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ
     * @param array $selectedProperties РњР°СЃСЃРёРІ СЃ ID СЃРІРѕР№СЃС‚РІ
     */
    public function addPropertiesToSet(int $setId, array $selectedProperties): OperationResult {
        if (empty($selectedProperties)) {
            return OperationResult::success(['set_id' => $setId, 'property_ids' => []], '', 'noop');
        }
        foreach ($selectedProperties as $propertyId) {
            $insertQuery = "INSERT INTO ?n (set_id, property_id) VALUES (?i, ?i)";
            $result = SafeMySQL::gi()->query($insertQuery, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $setId, $propertyId);
            if (!$result) {
                Logger::error('property_sets', 'Не удалось добавить свойство в набор', [
                    'set_id' => $setId,
                    'property_id' => $propertyId,
                    'sql' => SafeMySQL::gi()->lastQuery(),
                ]);
                return OperationResult::failure('Не удалось добавить свойство в набор', 'property_set_link_insert_failed', [
                    'set_id' => $setId,
                    'property_id' => $propertyId,
                ]);
            }
        }
        return OperationResult::success(['set_id' => $setId, 'property_ids' => array_values($selectedProperties)], '', 'linked');
    }

    /**
     * РџРѕР»СѓС‡Р°РµС‚ Р·РЅР°С‡РµРЅРёСЏ СЃРІРѕР№СЃС‚РІ РґР»СЏ РѕРїСЂРµРґРµР»РµРЅРЅРѕР№ СЃСѓС‰РЅРѕСЃС‚Рё
     * @param int $entity_id РРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ СЃСѓС‰РЅРѕСЃС‚Рё, РґР»СЏ РєРѕС‚РѕСЂРѕР№ С‚СЂРµР±СѓРµС‚СЃСЏ РїРѕР»СѓС‡РёС‚СЊ СЃРІРѕР№СЃС‚РІР°
     * @param string $entityType РўРёРї СЃСѓС‰РЅРѕСЃС‚Рё ('category', 'entity' Рё С‚.Рґ.)
     * @param int $propertyId ID СЃРІРѕР№СЃС‚РІР°
     * @param int $setId ID РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР°, РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 'RU'
     * @return array Р’РѕР·РІСЂР°С‰Р°РµС‚ РјР°СЃСЃРёРІ Р·РЅР°С‡РµРЅРёР№ СЃРІРѕР№СЃС‚РІ РґР»СЏ СЃСѓС‰РЅРѕСЃС‚Рё РёР»Рё РїСѓСЃС‚РѕР№ РјР°СЃСЃРёРІ, РµСЃР»Рё СЃРІРѕР№СЃС‚РІР° РЅРµ РЅР°Р№РґРµРЅС‹
     */
    public function getPropertyValuesForEntity(int $entity_id, string $entityType, int $propertyId, int $setId, string $languageCode = ENV_DEF_LANG): array {
        $sql = 'SELECT * FROM ?n WHERE entity_id = ?i AND entity_type = ?s AND property_id = ?i AND set_id = ?i AND language_code = ?s';
        $property = SafeMySQL::gi()->getRow($sql, Constants::PROPERTY_VALUES_TABLE, $entity_id, $entityType, $propertyId, $setId, $languageCode);
        if (!$property || !$property['property_values'] || $property['property_values'] == 'null') {
            return [];
        }
        // РџСЂРµРѕР±СЂР°Р·РѕРІР°РЅРёРµ Р·РЅР°С‡РµРЅРёР№ JSON РІ РјР°СЃСЃРёРІС‹ PHP, РµСЃР»Рё РЅРµРѕР±С…РѕРґРёРјРѕ
        if (is_string($property['property_values'])) {
            $property['property_values'] = json_decode($property['property_values'], true);
        }
        $property['fields'] = $property['property_values'];
        unset($property['property_values']);
        return $property;
    }

    /**
     * Р’РµСЂРЅС‘С‚ РјР°СЃСЃРёРІ РІСЃРµС… Р·РЅР°С‡РµРЅРёР№ СЃРІРѕР№СЃС‚РІ СЃСѓС‰РЅРѕСЃС‚Рё
     * @param int $entityIds РРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ СЃСѓС‰РЅРѕСЃС‚Рё, РґР»СЏ РєРѕС‚РѕСЂРѕР№ С‚СЂРµР±СѓРµС‚СЃСЏ РїРѕР»СѓС‡РёС‚СЊ СЃРІРѕР№СЃС‚РІР°
     * @param string $entityType РўРёРї СЃСѓС‰РЅРѕСЃС‚Рё ('category', 'page' Рё С‚.Рґ.)
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР°, РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 'RU'
     * @return array Р’РѕР·РІСЂР°С‰Р°РµС‚ РјР°СЃСЃРёРІ Р·РЅР°С‡РµРЅРёР№ СЃРІРѕР№СЃС‚РІ РґР»СЏ СЃСѓС‰РЅРѕСЃС‚Рё РёР»Рё РїСѓСЃС‚РѕР№ РјР°СЃСЃРёРІ, РµСЃР»Рё СЃРІРѕР№СЃС‚РІР° РЅРµ РЅР°Р№РґРµРЅС‹
     */
    public function getPropertiesValuesForEntity(int|array $entityIds, string $entityType, string $languageCode = ENV_DEF_LANG): array {
        if (!is_array($entityIds)) {
            $entityIds = [$entityIds];
        }
        $sql = 'SELECT * FROM ?n WHERE entity_id IN (?a) AND entity_type = ?s AND language_code = ?s';
        return SafeMySQL::gi()->getAll($sql, Constants::PROPERTY_VALUES_TABLE, $entityIds, $entityType, $languageCode);
    }

    /**
     * РЈРґР°Р»СЏРµС‚ РІСЃРµ Р·РЅР°С‡РµРЅРёСЏ СЃРІРѕР№СЃС‚РІ РґР»СЏ СѓРєР°Р·Р°РЅРЅС‹С… СЃСѓС‰РЅРѕСЃС‚РµР№(Рё) РѕРґРЅРѕРіРѕ С‚РёРїР° Рё СЏР·С‹РєР°
     * @param int|array $entityIds РРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ(С‹) СЃСѓС‰РЅРѕСЃС‚Рё
     * @param string $entityType РўРёРї СЃСѓС‰РЅРѕСЃС‚Рё ('category', 'page' Рё С‚Рґ)
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ ENV_DEF_LANG)
     * @return array|ErrorLogger Р’РѕР·РІСЂР°С‰Р°РµС‚ РїСѓСЃС‚РѕР№ РјР°СЃСЃРёРІ РІ СЃР»СѓС‡Р°Рµ СѓСЃРїРµС…Р° РёР»Рё РѕР±СЉРµРєС‚ ErrorLogger СЃ РёРЅС„РѕСЂРјР°С†РёРµР№ РѕР± РѕС€РёР±РєРµ
     */
    public function deleteAllpropertiesValuesEntity(int|array $entityIds, string $entityType, string $languageCode = ENV_DEF_LANG): OperationResult {
        if (!is_array($entityIds)) {
            $entityIds = [$entityIds];
        }
        $entityIds = array_filter(array_map('intval', $entityIds), fn($id) => $id > 0);
        if (empty($entityIds)) {
            return OperationResult::validation('Не переданы валидные ID сущностей', ['entity_ids' => $entityIds]);
        }
        if (empty(trim($entityType))) {
            return OperationResult::validation('Не указан тип сущности');
        }
        if (empty(trim($languageCode))) {
            return OperationResult::validation('Не указан код языка');
        }
        try {
            $sql = 'DELETE FROM ?n WHERE entity_id IN (?a) AND entity_type = ?s AND language_code = ?s';
            SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $entityIds, $entityType, $languageCode);
            return OperationResult::success(['entity_ids' => $entityIds, 'entity_type' => $entityType], '', 'deleted');
        } catch (\Throwable $e) {
            Logger::error('property_values', 'Исключение при удалении свойств: ' . $e->getMessage(), [
                'entityIds' => $entityIds,
                'entityType' => $entityType,
                'languageCode' => $languageCode,
                'exception' => $e
            ], [
                'initiator' => __FUNCTION__,
                'include_trace' => true,
            ]);
            return OperationResult::failure('Исключение при удалении свойств: ' . $e->getMessage(), 'delete_properties_exception', [
                'entityIds' => $entityIds,
                'entityType' => $entityType,
                'languageCode' => $languageCode,
            ]);
        }
    }

    /**
     * РЎРѕС…СЂР°РЅСЏРµС‚ РёР»Рё РѕР±РЅРѕРІР»СЏРµС‚ Р·РЅР°С‡РµРЅРёРµ СЃРІРѕР№СЃС‚РІР° РґР»СЏ СЃСѓС‰РЅРѕСЃС‚РµР№
     * @param mixed $propertyData Р”Р°РЅРЅС‹Рµ СЃРІРѕР№СЃС‚РІР° РґР»СЏ СЃРѕС…СЂР°РЅРµРЅРёСЏ РёР»Рё РѕР±РЅРѕРІР»РµРЅРёСЏ
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РґР»СЏ РґР°РЅРЅС‹С… СЃРІРѕР№СЃС‚РІР°, РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ 'RU'
     * @return mixed Р’РѕР·РІСЂР°С‰Р°РµС‚ РёРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ Р·Р°РїРёСЃРё РІ СЃР»СѓС‡Р°Рµ СѓСЃРїРµС…Р° РёР»Рё false РІ СЃР»СѓС‡Р°Рµ РѕС€РёР±РєРё
     */
    public function updatePropertiesValueEntities(array $propertyData = [], string $languageCode = ENV_DEF_LANG): OperationResult {
        static $propertyEntityTypeCache = [];
        static $propertyRowCache = [];
        static $existingValueCache = [];

        $propertyData['property_values'] = !empty($propertyData['fields']) ? $propertyData['fields'] : (!empty($propertyData['property_values']) ? $propertyData['property_values'] : false);
        $propertyData = SafeMySQL::gi()->filterArray($propertyData, SysClass::ee_getFieldsTable(Constants::PROPERTY_VALUES_TABLE));
        $propertyData = SysClass::ee_trimArrayValues($propertyData);
        $propertyData['language_code'] = $languageCode;
        if (empty($propertyData['entity_id']) || empty($propertyData['property_id']) || empty($propertyData['entity_type']) || empty($propertyData['property_values']) || empty($propertyData['set_id'])) {
            return OperationResult::validation('Не переданы обязательные поля для сохранения значения свойства', $propertyData);
        }
        $propertyId = (int) $propertyData['property_id'];
        if (!array_key_exists($propertyId, $propertyEntityTypeCache)) {
            $propertyEntityTypeCache[$propertyId] = (string) SafeMySQL::gi()->getOne(
                'SELECT entity_type FROM ?n WHERE property_id = ?i',
                Constants::PROPERTIES_TABLE,
                $propertyId
            );
        }
        $propEntityType = $propertyEntityTypeCache[$propertyId];
        if ($propertyData['entity_type'] !== $propEntityType && $propEntityType !== 'all') {
            return OperationResult::failure('Свойство недоступно для указанного типа сущности', 'property_value_entity_type_mismatch', $propertyData);
        }
        if (!array_key_exists($propertyId, $propertyRowCache)) {
            $propertyRowCache[$propertyId] = SafeMySQL::gi()->getRow(
                'SELECT p.name, p.type_id, p.default_values, p.is_multiple, p.is_required, pt.fields AS type_fields
                 FROM ?n AS p
                 LEFT JOIN ?n AS pt ON pt.type_id = p.type_id
                 WHERE p.property_id = ?i
                 LIMIT 1',
                Constants::PROPERTIES_TABLE,
                Constants::PROPERTY_TYPES_TABLE,
                $propertyId
            );
        }
        $propertyRow = $propertyRowCache[$propertyId];
        if (!$propertyRow) {
            return OperationResult::failure('Свойство не найдено для сохранения значения', 'property_value_property_not_found', $propertyData);
        }
        $propertyData['property_values'] = PropertyFieldContract::normalizeValueFieldsForStorage(
            $propertyData['property_values'],
            $propertyRow['default_values'] ?? [],
            $propertyRow['type_fields'] ?? [],
            $propertyRow
        );
        if (empty($propertyData['property_values'])) {
            return OperationResult::failure('Не удалось нормализовать значение свойства', 'property_value_normalize_failed', $propertyData);
        }
        if (is_array($propertyData['property_values'])) {
            $propertyData['property_values'] = json_encode($propertyData['property_values'], JSON_UNESCAPED_UNICODE);
            if (!$propertyData['property_values']) {
                return OperationResult::failure('Не удалось сериализовать значение свойства', 'property_value_json_encode_failed', $propertyData);
            }
        }
        $valueId = $propertyData['value_id'] ?? null;
        unset($propertyData['value_id']);
        if (is_int($valueId) || (is_string($valueId) && ctype_digit($valueId))) {
            $valueId = (int) $valueId;
        } else {
            $valueId = null;
        }
        $existingValueCacheKey = implode('|', [
            (int) $propertyData['entity_id'],
            $propertyId,
            (string) $propertyData['entity_type'],
            (int) $propertyData['set_id'],
            (string) $languageCode,
        ]);
        $existingValueRow = null;
        if (empty($valueId)) {
            if (!array_key_exists($existingValueCacheKey, $existingValueCache)) {
                $existingValueCache[$existingValueCacheKey] = SafeMySQL::gi()->getRow(
                    'SELECT value_id, property_values FROM ?n WHERE entity_id = ?i AND property_id = ?i AND entity_type = ?s AND set_id = ?i AND language_code = ?s LIMIT 1',
                    Constants::PROPERTY_VALUES_TABLE,
                    $propertyData['entity_id'],
                    $propertyId,
                    $propertyData['entity_type'],
                    $propertyData['set_id'],
                    $languageCode
                );
            }
            $existingValueRow = is_array($existingValueCache[$existingValueCacheKey]) ? $existingValueCache[$existingValueCacheKey] : null;
            $valueId = (int) ($existingValueRow['value_id'] ?? 0);
        } elseif (array_key_exists($existingValueCacheKey, $existingValueCache) && is_array($existingValueCache[$existingValueCacheKey])) {
            $existingValueRow = $existingValueCache[$existingValueCacheKey];
        }
        if ($existingValueRow !== null && (string) ($existingValueRow['property_values'] ?? '') === (string) $propertyData['property_values']) {
            return OperationResult::success((int) $valueId, '', 'noop');
        }
        Hook::run('preUpdatePropertiesValueEntities', $valueId, $propertyData);
        if (!empty($valueId)) {
            $sql = "UPDATE ?n SET ?u WHERE value_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $propertyData, $valueId);
            $action = 'update';
        } else {
            $sql = "INSERT INTO ?n SET ?u";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $propertyData);
            $valueId = SafeMySQL::gi()->insertId();
            $action = 'insert';
        }
        if (!$result || empty($valueId)) {
            Logger::error('property_values', 'Не удалось сохранить значение свойства', [
                'property_data' => $propertyData,
                'action' => $action,
                'sql' => SafeMySQL::gi()->lastQuery(),
            ]);
            return OperationResult::failure('Не удалось сохранить значение свойства', 'property_value_save_failed', [
                'property_data' => $propertyData,
                'action' => $action,
            ]);
        }
        $existingValueCache[$existingValueCacheKey] = [
            'value_id' => (int) $valueId,
            'property_values' => (string) $propertyData['property_values'],
        ];
        Hook::run('postUpdatePropertiesValueEntities', $valueId, $propertyData, $action);
        return OperationResult::success((int) $valueId, '', $action);
    }

    /**
     * Р—Р°РїРёСЃС‹РІР°РµС‚ РґРµС„РѕР»С‚РЅС‹Рµ Р·РЅР°С‡РµРЅРёСЏ СЃСѓС‰РЅРѕСЃС‚Рё РІ РµС‘ С‚РµРєСѓС‰РёРµ
     * @param string $entityType РўРёРї СЃСѓС‰РЅРѕСЃС‚Рё 'category', 'page'
     * @param int $entityId ID СЃСѓС‰РЅРѕСЃС‚Рё
     * @param array $entityData Р”Р°РЅРЅС‹Рµ СЃСѓС‰РЅРѕСЃС‚Рё
     * @return bool
     */
    public function createPropertiesValueEntities(string $entityType, int $entityId, array $entityData = []): void {
        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        $languageCode = strtoupper(trim((string) ($entityData['language_code'] ?? ENV_DEF_LANG)));
        if ($languageCode === '') {
            $languageCode = strtoupper((string) ENV_DEF_LANG);
        }
        $typeId = $objectModelCategories->getCategoryTypeId((int) ($entityData['category_id'] ?? 0), $languageCode);
        $setIds = $objectModelCategoriesTypes->getCategoriesTypeSetsData($typeId);
        foreach ($setIds as $setId) {
            $setData = $this->getPropertySetData($setId, $languageCode);
            foreach ($setData['properties'] as $propertyId => $property) {
                if ($property['property_entity_type'] == $entityType || $property['property_entity_type'] == 'all') {
                    $propertyData = [
                        'entity_id' => $entityId,
                        'property_id' => $propertyId,
                        'entity_type' => $entityType,
                        'set_id' => $setId,
                        'property_values' => $property['default_values']
                    ];
                    $this->updatePropertiesValueEntities($propertyData, $languageCode);
                }
            }
        }
    }

    /**
     * РЈРґР°Р»СЏРµС‚ СЃРІСЏР·Рё РјРµР¶РґСѓ РЅР°Р±РѕСЂРѕРј Рё РєРѕРЅРєСЂРµС‚РЅС‹РјРё СЃРІРѕР№СЃС‚РІР°РјРё
     * @param int   $setId        ID РЅР°Р±РѕСЂР° СЃРІРѕР№СЃС‚РІ
     * @param array $propertyIds  РњР°СЃСЃРёРІ ID СЃРІРѕР№СЃС‚РІ РґР»СЏ СѓРґР°Р»РµРЅРёСЏ РёР· РЅР°Р±РѕСЂР°
     */
    public function deletePropertiesFromSet(int $setId, array $propertyIds): OperationResult {
        if (empty($propertyIds)) {
            return OperationResult::success(['set_id' => $setId, 'property_ids' => []], '', 'noop');
        }
        $result = SafeMySQL::gi()->query(
            "DELETE FROM ?n WHERE set_id = ?i AND property_id IN (?a)",
            Constants::PROPERTY_SET_TO_PROPERTIES_TABLE,
            $setId,
            $propertyIds
        );
        if (!$result) {
            Logger::error('property_sets', 'Не удалось удалить свойства из набора', [
                'set_id' => $setId,
                'property_ids' => $propertyIds,
                'sql' => SafeMySQL::gi()->lastQuery(),
            ]);
            return OperationResult::failure('Не удалось удалить свойства из набора', 'property_set_link_delete_failed', [
                'set_id' => $setId,
                'property_ids' => $propertyIds,
            ]);
        }
        return OperationResult::success(['set_id' => $setId, 'property_ids' => array_values($propertyIds)], '', 'unlinked');
    }
}
