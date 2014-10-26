<?php

class Util_Csv {
    /**
     * @param NewModel_Presence[]|Model_Campaign[] $models
     * @param Header_Abstract[] $headers
     * @return array
     */
    static function generateCsvData($models, $headers) {
        /*if (userHasNoPermissions) {
            $this->view->msg = 'This file cannot be downloaded!';
            $this->_forward('error', 'download');
            return FALSE;
        }*/

        $csvData = array(
            array(),
            array()
        );

        /** @var Header_Abstract[] $columns */
        $columns = array();
        foreach ($headers as $column) {
            if ($column->isForCsv()) {
                $columns[] = $column;
                $csvData[0][] = $column->getLabel();
                $csvData[1][] = $column->getDescription();
            }
        }

        foreach($models as $model){
            $dataTypes = array();
            if ($model instanceof Model_Campaign) {
                $dataTypes[] = Header_Abstract::MODEL_TYPE_CAMPAIGN;
                if ($model instanceof Model_Country) {
                    $dataTypes[] = Header_Abstract::MODEL_TYPE_COUNTRY;
                } else if ($model instanceof Model_Group) {
                    $dataTypes[] = Header_Abstract::MODEL_TYPE_GROUP;
                } else if ($model instanceof Model_Region) {
                    $dataTypes[] = Header_Abstract::MODEL_TYPE_REGION;
                }
            } else if ($model instanceof NewModel_Presence) {
                $dataTypes[] = Header_Abstract::MODEL_TYPE_PRESENCE;
            }

            $row = array();
            foreach($columns as $column) {
                $output = null;
                foreach ($dataTypes as $type) {
                    if ($column->isAllowedType($type)) {
                        $output = $column->getValue($model);
                        break;
                    }
                }
                $row[] = $output;
            }
            $csvData[] = $row;
        }

        return $csvData;
    }

    static function outputCsv($data, $filename) {
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename={$filename}.csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        $output = fopen("php://output", "w");
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}