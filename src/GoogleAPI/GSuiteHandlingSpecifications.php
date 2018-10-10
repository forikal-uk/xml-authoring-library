<?php
/**
 * Created by PhpStorm.
 * User: jw
 * Date: 10/10/2018
 * Time: 09:02
 */

namespace XmlSquad\Library\GoogleAPI;

use XmlSquad\Library\GoogleAPI\GSuiteHandlingSpecificationsInterface;


/**
 * Default set of specifications to use if you are too lazy to create your own.
 *
 * Concrete class that implements the GSuiteHandlingSpecificationsInterface and can be extended to avoid duplicating code.
 *
 * Ignores sheets filenames ending in underscores_ or underscores_.<filenameSuffix>.
 * Ignores sheet tabs ending in underscores_ or underscores_.<filenameSuffix>.
 * Limits the range of columns up to ZZ when getting data from the sheets.
 * Allows extra colummns in the target sheets when checking for headerValues
 *  (as long as all the targetted columns are present).
 *
 */
class GSuiteHandlingSpecifications implements GSuiteHandlingSpecificationsInterface
{

    /**
     * Default Range limit when getting data from spreadsheet.
     */
    const DEFAULT_COLUMN_RANGE_LIMIT = 'ZZ';


    /**
     * @var array The headers that we care about in the target sheets.
     */
    protected $targettedHeadingValues = [];

    public function __construct($targettedHeadingValues)
    {
        $this->targettedHeadingValues = $targettedHeadingValues;
    }


    /**
     * Range limit when getting data from spreadsheet.
     *
     * Implements GSuiteHandlingSpecificationsInterface
     *
     * @return string Column identity of maximum range to get from sheet.
     */
    public function getColumnRangeLimit(): string
    {
        return self::DEFAULT_COLUMN_RANGE_LIMIT;
    }

    /**
     * If a file is called foo_, then it is assumed to be 'private' and should be explicitly ignored,
     *
     * Implements GSuiteHandlingSpecificationsInterface
     *
     * Test if full file name ends with _ or only filename without the extension
     * i.e. foo_.xlsx and foo__
     *
     * @param $fullName of Google Sheet
     * @return bool
     */
    public function isGSheetFileNameIgnored($fullName): bool
    {

        $nameWithoutExtension = explode('.', $fullName)[0];

        if (
            '_' === substr($fullName, -1) ||
            '_' === substr($nameWithoutExtension, -1)
        ) {
            // @todo feedback that this file was ignored?
            return true;
        }

        return false;
    }

    /**
     *  If a Google Sheet's tab is named foo_, then it is assumed to be 'private'.
     *
     *  Implements GSuiteHandlingSpecificationsInterface
     *
     * @param $title
     * @return bool
     */
    public function isGSheetTabNameIgnored($title): bool
    {
        if ('_' === substr($title, -1)) {
            // @todo feedback that this sheet/tab was ignored?
            return true;
        }
        return false;
    }

    /**
     * Given a row of values, determines if it looks like a header row
     *
     * Implements GSuiteHandlingSpecificationsInterface
     *
     *
     * @param array|null $row
     * @return bool
     */
    public function isHeadingsRow(?array $row): bool
    {
        return $this->isAllHeadingValuesPresentInRow($row);
    }

    /**
     * Allows additional columns in the spreadsheet, as long as all the targetted columns are present.
     *
     * @param array|null $row
     * @return bool
     */
    protected function isAllHeadingValuesPresentInRow(?array $row){

        if (true === empty($row)) {
            return false;
        }

        foreach($this->targettedHeadingValues as $headerValue){

            if (false === in_array(trim($headerValue), $row)) {
                //print($headerValue . ' not in ' . print_r($row,true));
                return false;
            }

        }

        return true;
    }





}