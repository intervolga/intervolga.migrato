<? namespace Intervolga\Migrato\Data;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use Intervolga\Migrato\Tool\ExceptionText;
use Intervolga\Migrato\Tool\XmlIdProvider\OrmXmlIdProvider;

/**
 * Class BaseOrmData - базовый класс для миграции ORM-сущностей.
 * Реализует все необходимые методы для миграции ORM-сущности.<br><br>
 *
 * Ограничения и особенности:
 * * ORM-сущность должна быть наследником \Bitrix\Main\Entity\DataManager.
 * * ORM-сущность должна иметь строковое поле XML_ID с уникальным идентификатором.
 * * Поля ReferenceField не мигрируются.
 * * Поля ExpressionField не мигрируются.
 * * Поля IntegerField с именеи ID не мигрируются.
 * * Поля UserField не мигрируются.
 *
 * Использование:
 *  1. Создать класс-наследник для мигрируемой ORM-сущности, реализовов в нем метод configureOrm().
 *  2. Добавить сущность в config файл migrato.
 *  3. Добавить класс-наследник в список классов migrato с помощью обработчика события OnMigratoDataBuildList.
 *
 * @package Intervolga\Migrato\Data
 */
abstract class BaseOrmData extends BaseData
{
    const XML_ID_FIELD_NAME = 'XML_ID';
    const ORM_DATE_FIELD_CLASS_NAME = '\Bitrix\Main\Entity\DateField';
    const ORM_ENTITY_PARENT_CLASS_NAME = '\Bitrix\Main\Entity\DataManager';
    const ORM_INTEGER_FIELD_CLASS_NAME = '\Bitrix\Main\Entity\IntegerField';
    const ORM_BOOLEAN_FIELD_CLASS_NAME = '\Bitrix\Main\Entity\BooleanField';
    const ORM_DATETIME_FIELD_CLASS_NAME = '\Bitrix\Main\Entity\DatetimeField';
    const ORM_REFERENCE_FIELD_CLASS_NAME = '\Bitrix\Main\Entity\ReferenceField';
    const ORM_EXPRESSION_FIELD_CLASS_NAME = '\Bitrix\Main\Entity\ExpressionField';

    protected $moduleName = '';
    /**
     * @var string|\Bitrix\Main\Entity\DataManager
     */
    protected $ormEntityClass = '';

    /**
     * Получить абсолютное имя класса ORM-сущности.
     *
     * @return string|\Bitrix\Main\Entity\DataManager абсолютное имя класса ORM-сущности.
     */
    public function getOrmEntityClass()
    {
        return $this->ormEntityClass;
    }

    /**
     * Установить абсолютное имя класса ORM-сущности.
     *
     * @param string|\Bitrix\Main\Entity\DataManager $ormEntityClass абсолютное имя класса ORM-сущности.
     */
    public function setOrmEntityClass($ormEntityClass)
    {
        $this->ormEntityClass = $ormEntityClass;
    }

    /**
     * Получить название модуля ORM-сущности.
     *
     * @return string название модуля ORM-сущности.
     */
    public function getModule()
    {
        return $this->moduleName;
    }

    /**
     * Установить название модуля ORM-сущности.
     *
     * @param string $moduleName название модуля ORM-сущности.
     */
    public function setModule($moduleName)
    {
        $this->moduleName = $moduleName;
    }

    public function update(Record $record)
    {
        $dataManger = $this->ormEntityClass;
        $id = $record->getId()->getValue();
        $recordAsArray = $this->recordToArray($record);

        $updateResult = $dataManger::update($id, $recordAsArray);
        if(!$updateResult->isSuccess())
        {
            throw new \Exception(ExceptionText::getFromResult($updateResult));
        }
    }

    public function getList(array $filter = array())
    {
        $ormEntityElements = [];
        $dataManager = $this->ormEntityClass;

        $getListResult = $dataManager::getList();
        while($ormEntityElement = $getListResult->fetch())
        {
            $record = new Record($this);
            $recordId = $this->createId($ormEntityElement['ID']);
            $record->setId($recordId);
            $record->setXmlId($ormEntityElement['XML_ID']);
            $record->addFieldsRaw($this->getElementFields($ormEntityElement));

            $ormEntityElements[] = $record;
        }

        return $ormEntityElements;
    }

    /**
     * Настраивает базовый класс для работы с ORM-сущностью.
     * Необходимо реализовать данный метод в классе-наследнике, задав
     * название модуля, абсолютное имя класса ORM-сущности и имя ORM-сущности
     * на естественном языке (опционально, для логов). <br><br>
     *
     * Для названия модуля использовать метод - setModule().<br>
     * Для имени класса ORM-сущности использовать метод - setOrmEntityClass(). <br>
     * Для названия ORM-сущности на естественном языке, использовать метод - setEntityNameLoc().<br>
     * Если имя сущности на естественном языке не задано,
     * будет использовано имя класса.
     */
    abstract public function configureOrm();

    protected function configure()
    {
        $this->configureOrm();
        $this->processUserOrmEntity();
        $this->xmlIdProvider = new OrmXmlIdProvider($this, $this->ormEntityClass);

    }

    protected function createInner(Record $record)
    {
        $dataManager = $this->ormEntityClass;
        $recordAsArray = $this->recordToArray($record);

        $addResult = $dataManager::add($recordAsArray);
        if($addResult->isSuccess())
        {
            return $this->createId($addResult->getId());
        }
        else
        {
            throw new \Exception(ExceptionText::getFromResult($addResult));
        }
    }

    protected function deleteInner(RecordId $id)
    {
        $dataManager = $this->ormEntityClass;

        $deleteResult = $dataManager::delete($id->getValue());
        if(!$deleteResult->isSuccess())
        {
            throw new \Exception(ExceptionText::getFromResult($deleteResult));
        }
    }

    /**
     * Проверяет корректность настройки ORM-сущности:
     * * Корректность задания модуля
     * * Корректность задания класса ORM-сущности
     * * Наличие поля XML_ID в ORM-сущности.
     * <br><br>
     * Производит окончательную настройку для работы
     * с ORM-сущностью.
     *
     * @throws ArgumentException в случае некорректной ORM-сущности.
     * @throws \Bitrix\Main\LoaderException в случае неверно заданного модуля.
     */
    protected function processUserOrmEntity()
    {
        if(Loader::includeModule($this->moduleName))
        {
            $this->processEntityClassName($this->ormEntityClass);
            $this->processEntityName($this->entityNameLoc);
            $this->checkXmlId();
        }
        else
        {
            $exceptionMessage = Loc::getMessage(
                'INTERVOLGA_MIGRATO.ORM_ENTITY.MODULE_NOT_INCLUDED',
                [
                    '#ORM_ENTITY#' => $this->ormEntityClass,
                    '#MODULE_NAME#' => $this->moduleName

                ]
            );
            throw new ArgumentException($exceptionMessage);
        }
    }

    /**
     * Получение полей элемента для метода Record::addFieldsRaw().
     *
     * @param array $ormEntityElement запись элемента из БД.
     * @return array ассоциативный массив полей элемента.
     */
    protected function getElementFields($ormEntityElement)
    {
        $fieldsRaw = [];
        $dataManager = $this->ormEntityClass;
        $integerField = static::ORM_INTEGER_FIELD_CLASS_NAME;
        $referenceField = static::ORM_REFERENCE_FIELD_CLASS_NAME;
        $expressionField = static::ORM_EXPRESSION_FIELD_CLASS_NAME;

        $fields = $dataManager::getEntity()->getFields();
        foreach ($fields as $fieldName => $field)
        {
            // Do not migrate ExpressionField and ReferenceField
            if ($field instanceof $expressionField ||
                $field instanceof $referenceField)
            {
                continue;
            }
            // Do not migrate integer field with name 'ID'
            elseif($field instanceof $integerField && $field->getName() == 'ID')
            {
                continue;
            }
            else
            {
                $fieldsRaw[$fieldName] = $ormEntityElement[$fieldName];
            }
        }

        return $fieldsRaw;
    }

    /**
     * Обработка имени класса ORM-сущности при конфигурировании.
     *
     * @param string|\Bitrix\Main\Entity\DataManager $entityClassName абсолютное имя класса ORM-сущности.
     * @throws ArgumentException в случае некорректной ORM-сущности.
     */
    protected function processEntityClassName($entityClassName)
    {
        if(empty($entityClassName))
        {
            throw new ArgumentException(Loc::getMessage('INTERVOLGA_MIGRATO.ORM_ENTITY.NOT_SET'));
        }

        if(is_string($entityClassName))
        {
            if($entityClassName[0] !== '\\')
            {
                $entityClassName = '\\' . $entityClassName;
            }
        }
        else
        {
            $exceptionMessage = $this->getMessageWithOrmEntity(
                'INTERVOLGA_MIGRATO.ORM_ENTITY.NOT_STRING_ARGUMENT',
                $entityClassName
            );
            throw new ArgumentException($exceptionMessage);
        }

        $isCorrect = $this->isEntityClassNameCorrect($entityClassName);
        if($isCorrect->result != true)
        {
            throw new ArgumentException($isCorrect->message);
        }
    }

    /**
     * Обработка названия ORM-сущности на естественном языке при конфигурировании.
     * Если название не задано явно, название берется из абсолютного имени класса
     * ORM-сущности.
     *
     * @param string $entityName название ORM-сущности на естественном языке.
     */
    protected function processEntityName($entityName)
    {
        if($entityName == '')
        {
            $this->entityNameLoc = substr(strrchr($this->ormEntityClass, '\\'), 1);
        }
    }

    /**
     * Проверка корректности имени класса ORM-сущности:
     * * Проверка, что класс ORM-сущности существует.
     * * Проверка, что класс ORM-сущности является наследником \Bitrix\Main\Entity\DataManager.
     *
     * @param string|\Bitrix\Main\Entity\DataManager $entityClassName абсолютное имя класса ORM-сущности.
     * @return object объект с флагом результата и сообщением, в случае, если проверка не пройдена.
     */
    protected function isEntityClassNameCorrect($entityClassName)
    {
        $result = null;

        try
        {
            $entityReflectionClass = new \ReflectionClass($entityClassName);

            if(!$entityReflectionClass->isSubclassOf(static::ORM_ENTITY_PARENT_CLASS_NAME))
            {
                $result = $this->getResultObject(
                    false,
                    $this->getMessageWithOrmEntity(
                        'INTERVOLGA_MIGRATO.ORM_ENTITY.NOT_PROPER_SUBCLASS',
                        $entityClassName
                    )
                );
            }
            else
            {
                $result = $this->getResultObject(true);
            }
        }
        catch(\ReflectionException $exc)
        {
            $result = $this->getResultObject(
                false,
                $this->getMessageWithOrmEntity(
                    'INTERVOLGA_MIGRATO.ORM_ENTITY.NOT_EXISTS',
                    $entityClassName
                )
            );
        }

        return $result;
    }

    /**
     * Формирует объект результата.
     *
     * @param bool $result значение флага результата.
     * @param string $message сообщение, поясняющее флаг результата.
     * @return object объект результата с полями result и message.
     */
    protected function getResultObject($result, $message = 'ok')
    {
        return (object)[
            'result' => $result,
            'message' => $message
        ];
    }

    /**
     * Возвращает языковую константу, подставляя имя класса ORM-сущности.
     *
     * @param string $messageCode ключ языковой константы.
     * @param string|\Bitrix\Main\Entity\DataManager $entityClassName имя класса ORM-сущности.
     * @return string языковая константа с именем класса ORM-сущности.
     */
    protected function getMessageWithOrmEntity($messageCode, $entityClassName)
    {
        return Loc::getMessage(
            $messageCode,
            ['#ORM_ENTITY#' => $entityClassName]
        );
    }

    /**
     * Получение элмента в виде массива из объекта Record
     * для методов createInner() и update().
     *
     * @param Record $record объект элемента.
     * @return string[] массив элемента.
     * @throws \Exception в случае ошибки привдения типов.
     */
    protected function recordToArray(Record $record)
    {
        $recordAsArray = $record->getFieldsRaw();
        $recordAsArray = $this->castFields($recordAsArray);

        return $recordAsArray;
    }

    /**
     * Приведение типов при импорте.
     *
     * @param string[] $recordAsArray элемент в виде массива.
     * @return string[]
     */
    protected function castFields($recordAsArray)
    {
        $dataManager = $this->ormEntityClass;
        $booleanField = static::ORM_BOOLEAN_FIELD_CLASS_NAME;
        $dataTimeField = static::ORM_DATETIME_FIELD_CLASS_NAME;
        $dateField = static::ORM_DATE_FIELD_CLASS_NAME;

        $fields = $dataManager::getEntity()->getFields();
        foreach ($fields as $field)
        {
            $fieldName = $field->getName();
            if($field instanceof $booleanField)
            {
                $recordAsArray[$fieldName] = $this->castBooleanField($field, $recordAsArray[$fieldName]);
            }
            elseif($field instanceof $dataTimeField)
            {
                $recordAsArray[$fieldName] = new DateTime($recordAsArray[$fieldName]);
            }
            elseif($field instanceof $dateField)
            {
                $recordAsArray[$fieldName] = new Date($recordAsArray[$fieldName]);
            }
        }

        return $recordAsArray;
    }

    /**
     * Приведение типа для BooleanFied полей при импорте.
     *
     * @param \Bitrix\Main\Entity\ScalarField $field объект поля ORM-сущности.
     * @param string $fieldValue значение поля элемента.
     * @return bool
     */
    protected function castBooleanField($field, $fieldValue)
    {
        if(is_numeric($fieldValue))
        {
            $fieldValue = intval($fieldValue);
        }
        $boolValues = $field->getValues();

        if($fieldValue == $boolValues[0])
        {
            if(is_bool($boolValues[0]))
            {
                $fieldValue = boolval($fieldValue);
            }
        }
        elseif($fieldValue == $boolValues[1])
        {
            if(is_bool($boolValues[1]))
            {
                $fieldValue = boolval($fieldValue);
            }
        }

        return $fieldValue;
    }

    /**
     * Проверка наличия поля XML_ID в ORM-сущности.
     *
     * @throws ArgumentException в случае отсутствия поля XML_ID.
     */
    protected function checkXmlId()
    {
        $dataManager = $this->ormEntityClass;
        $fields = $dataManager::getEntity()->getFields();
        $fieldNames = array_keys($fields);

        if(!in_array(static::XML_ID_FIELD_NAME, $fieldNames))
        {
            $exceptionMessage = $this->getMessageWithOrmEntity(
                'INTERVOLGA_MIGRATO.ORM_ENTITY.NO_XML_ID',
                $this->ormEntityClass

            );
            throw new ArgumentException($exceptionMessage);
        }
    }
}