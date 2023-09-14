<?php

namespace Vcian\PhpDbAuditor\Traits;

use Exception;
use Illuminate\Support\Facades\Log;
use Vcian\PhpDbAuditor\Constants\Constant;
use Vcian\PhpDbAuditor\Traits\DBConnection;
use Vcian\PhpDbAuditor\Traits\NamingRules;

trait Rules
{
    use NamingRules, DBConnection;

    /**
     * @var array
     */
    protected array $result;

    /**
     * Check table name rules
     * @return array
     */
    public function tablesRule(): array
    {
        $checkTableStandard = Constant::ARRAY_DECLARATION;
        try {
            $tableList = $this->getTableList();
            foreach ($tableList as $tableName) {
                $status = $this->checkStatus($tableName);
                $size = $this->getTableSize($tableName);
                $checkTableStandard[] = ["name" => $tableName, "status" => $status, "size" => $size];
            }
        } catch (Exception $exception) {
            error_log($exception->getMessage());;
        }
        return $checkTableStandard;
    }

    /**
     * Check Status for Tables and Fields
     * @param string $tableName
     * @return string
     */
    public function checkStatus(string $tableName): string
    {
        $status = Constant::STATUS_TRUE;
        $tableCheck = $this->checkRules($tableName, Constant::TABLE_RULES);
        if (empty($tableCheck)) {
            $status = Constant::STATUS_FALSE;
        } else {
            $filedDetails = $this->fieldRules($tableName);
            foreach ($filedDetails as $field) {
                unset($field['suggestion']);
                unset($field['datatype']);
                if (!empty($field)) {
                    $status = Constant::STATUS_FALSE;
                }
            }
        }
        return $status;
    }

    /**
     * Check Rules for Fields and Tables
     * @param string $name
     * @param string|null $type
     * @return array
     */
    public function checkRules(string $name, string $type = null): array
    {
        $messages = Constant::ARRAY_DECLARATION;
        try {
            $checkLowerCase = $this->nameOnlyLowerCase($name);
            $checkSpace = $this->nameHasNoSpace($name);
            $checkAlphabets = $this->nameHasOnlyAlphabets($name);

            if ($type === Constant::TABLE_RULES) {
                $checkLength = $this->nameHasFixLength($name);
                $checkNamePlural = $this->nameAlwaysPlural($name);

                if (!$checkLength) {
                    $messages[] = 'Table name should not be more than 64 characters';
                }

                if ($checkNamePlural !== Constant::STATUS_TRUE) {
                    $messages[] = 'Table name should be plural.' . "($checkNamePlural)";
                }
            }

            if ($checkSpace !== Constant::STATUS_TRUE) {
                $messages[] = 'Space between words is not advised. Please Use Underscore "_"' . "($checkSpace)";
            }

            if ($checkAlphabets !== Constant::STATUS_TRUE) {
                $messages[] = 'Numbers are not for names and is not advised! Please use alphabets for name.' . "($checkAlphabets)";
            }

            if ($checkLowerCase !== Constant::STATUS_TRUE) {
                $messages[] = 'Name should be in lowercase.' . "($checkLowerCase)";
            }
        } catch (Exception $exception) {
            error_log($exception->getMessage());
        }

        return $messages;
    }

    /**
     * Check field rules
     * @param string $tableName
     * @return array
     */
    public function fieldRules(string $tableName): array
    {
        $checkFields = Constant::ARRAY_DECLARATION;
        try {
            $fields = $this->getFields($tableName);

            foreach ($fields as $field) {
                $checkFields[$field] = $this->checkRules($field, Constant::FIELD_RULES);
                $dataTypeDetails = $this->getFieldDataType($tableName, $field);
                $checkFields[$field]['datatype'] = $dataTypeDetails;
                if ($dataTypeDetails['data_type'] === Constant::DATATYPE_VARCHAR && $dataTypeDetails['size'] <= Constant::DATATYPE_VARCHAR_SIZE) {
                    $checkFields[$field]['suggestion'] = 'Here you can use CHAR datatype instead of VARCHAR if data values in a column are of the same length.';
                }
            }
        } catch (Exception $exception) {
            error_log($exception->getMessage());;
        }
        return $checkFields;
    }

    /**
     * Check rules for single table and check table exist or not
     * @param string $tableName
     * @return array|bool
     */
    public function tableRules(string $tableName): array|bool
    {
        $checkTableStatus = Constant::ARRAY_DECLARATION;
        try {
            if ($tableName) {
                $tableExist = $this->checkTableExist($tableName);

                if (!$tableExist) {
                    return Constant::STATUS_FALSE;
                }

                $fields = $this->fieldRules($tableName);
                $tableComment = $this->checkRules($tableName, Constant::TABLE_RULES);
                $checkTableStatus = ["table" => $tableName, "table_comment" => $tableComment, "fields" => $fields];
            }
        } catch (Exception $exception) {
            error_log($exception->getMessage());;
        }
        return $checkTableStatus;
    }
}
