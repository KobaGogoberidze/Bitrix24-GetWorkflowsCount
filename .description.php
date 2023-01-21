<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Bizproc\FieldType;

$arActivityDescription = array(
    "NAME" => GetMessage("JC_GWC_NAME"),
    "DESCRIPTION" => GetMessage("JC_GWC_DESCRIPTION"),
    "TYPE" => "activity",
    "CLASS" => "JCGetWorkflowsCountActivity",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => array(
        "ID" => "other",
    ),
    "RETURN" => array(
        "Count" => array(
            "NAME" => GetMessage("JC_GWC_RETURN_COUNT_FIELD_TITLE"),
            "TYPE" => FieldType::INT,
        ),
    ),
);
