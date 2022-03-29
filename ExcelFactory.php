<?php

/**
 * Contains functions designed to streamline the creation of PHPExcel objects.
 */
class ExcelFactory
{
    /**
     * Shorthand for creating a new \PHPExcel object with some buit-in defaults.
     * @param string|null $title Optional. Meta title of the Excel Document.
     * @param string|null $description Optional. Meta description of the Excel Document.
     * @param string|null $default_worksheet Optional. Name of the active worksheet.
     * @return \PHPExcel
     */
    private static function makeDocument($title = null, $description = null, $default_worksheet = null)
    {
        $doc = new \PHPExcel;

        $doc->getProperties()
            ->setCreator("Specimen")
            ->setLastModifiedBy("Sample Web System")
            ->setTitle($title ?? "")
            ->setDescription($description ?? "");

        if ($default_worksheet !== null) $doc->getActiveSheet()->setTitle($default_worksheet);

        return $doc;
    }

    /**
     * Produces a PHPExcel representation of an Excel workbook using a simple array as a data source.
     * A "simple" array is one that features no deep nesting, or anything that'd require specific processing.
     * The data must instead be table-like - an array of arrays representing table rows. It will be cast directly onto
     * the spreadsheet.
     * @param array $array Table-like data source - an array of arrays (table rows).
     * @param string|null $document_title Optional. Meta title of the Excel Document.
     * @param string|null $document_description Optional. Meta description of the Excel Document.
     * @param string|null $sheet_name Optional. Name of the active worksheet.
     * @return \PHPExcel
     */
    public static function fromArray(
        array $array,
        string $document_title = null,
        string $document_description = null,
        string $sheet_name = null
    ) {
        $excel = self::makeDocument($document_title, $document_description, $sheet_name);
        $excel->getActiveSheet()->fromArray($array);

        return $excel;
    }

    /**
     * Constructs a new Excel sheet from a collection of rows, represented by associative arrays (dictionaries).
     * Rather than simply casting the array structure onto the spreadsheet - like seen in self::fromArray - it will
     * attempt to lay out columns and filter the data according to row keys.
     * @param array $array Table-like data source - an array of associative arrays (table rows).
     * @param array|null $keys Optional. Either a dictionary ([column name => row key]), or a list of row keys.
     *                         Used for filtering and structuring the data. First row's keys will be used by default.
     * @param string|null $document_title Optional. Meta title of the Excel Document.
     * @param string|null $document_description Optional. Meta description of the Excel Document.
     * @param string|null $sheet_name Optional. Name of the active worksheet.
     * @return \PHPExcel
     */
    public static function fromDictionary(
        array $array,
        array $keys = null,
        $document_title = null,
        $document_description = null,
        $sheet_name = null
    ) {
        $data_keys = $keys ?? array_keys($array[0] ?? []);
        $columns = [];

        foreach ($data_keys as $column_name => $key_name) {
            $columns[] = is_string($column_name) ? $column_name : $key_name;
        }

        $data = array_map(function($row) use($data_keys) {
            $filtered_row = [];
            foreach ($data_keys as $k) {
                $filtered_row[] = $row[$k] ?? "";
            }
            return $filtered_row;
        }, $array);

        array_unshift($data, $columns);

        return self::fromArray($data, $sheet_name, $document_title, $document_description);
    }
}
