<?php
/**
 * Created by PhpStorm.
 * User: jw
 * Date: 12/10/2018
 * Time: 07:36
 */

namespace XmlSquad\Library\GoogleAPI;


interface GoogleDriveProcessorInterface
{
    /**
     *
     *
     * @param array $domainGSheetObjects
     * @return mixed
     */
    public function processDomainGSheetObjects(array $domainGSheetObjects);
}