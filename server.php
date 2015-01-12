<?php

/**
 * server.php
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category  Server
 * @package   Appserver
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2014 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */

namespace AppserverIo\Appserver\Core;

use AppserverIo\Appserver\Core\Utilities\DirectoryKeys;

declare (ticks = 1);

error_reporting(~E_NOTICE);
set_time_limit(0);

// set the session timeout to unlimited
ini_set('session.gc_maxlifetime', 0);
ini_set('zend.enable_gc', 0);
ini_set('max_execution_time', 0);

// set environmental variables in $_ENV globals per default
$_ENV = appserver_get_envs();

// define a constant with the appserver base directory
define('APPSERVER_BP', __DIR__);

// load core functions to override in runtime environment
require __DIR__ . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'core_functions.php';

// bootstrap the application
require __DIR__ . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'bootstrap.php';

// initialize configuration and schema file name
$configurationFileName = DirectoryKeys::realpath(sprintf('%s/%s/appserver.xml', APPSERVER_BP, DirectoryKeys::CONF));
$schemaFileName = DirectoryKeys::realpath(sprintf('%s/resources/schema/appserver.xsd', APPSERVER_BP));

// activate internal error handling, necessary to catch errors with libxml_get_errors()
libxml_use_internal_errors(true);

// initialize the DOMDocument with the configuration file to be validated
$configurationFile = new \DOMDocument();
$configurationFile->load($configurationFileName);

// substitude xincludes
$configurationFile->xinclude(LIBXML_SCHEMA_CREATE);

// validate the configuration file with the schema
$validationFailed = false;
if ($configurationFile->schemaValidate($schemaFileName) === false) {
    $validationFailed = true;
    foreach (libxml_get_errors() as $error) {
        $message = "Found a schema validation error on line %s with code %s and message %s when validating configuration file %s";
        error_log(var_export($error, true));
        throw new \Exception(sprintf($message, $error->line, $error->code, $error->message, $error->file));
    }
}

// initialize the SimpleXMLElement with the content XML configuration file
$configuration = new \AppserverIo\Configuration\Configuration();
$configuration->initFromString($configurationFile->saveXml());
$configuration->addChildWithNameAndValue('baseDirectory', APPSERVER_BP);

// create the server instance
$server = new Server($configuration);

// check if server.php has been started with additional options
$watch = 'w';
$configTest = 't';
$arguments = getopt("$watch::$configTest::");

// if -w option has been passed, watch deployment directory only, if -t has been passed we tell them everything went fine (otherwise we would not have reached this point)
if (array_key_exists($configTest, $arguments)) {

    if ($validationFailed === true) {

        throw new \Exception('Syntax errors detected, see error log for further information.');

    } else {

        error_log('Syntax OK');
    }

} elseif (array_key_exists($watch, $arguments)) {

    $server->watch();

} else {
    $server->start();
    $server->profile();
}
