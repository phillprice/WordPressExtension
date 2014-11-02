<?php

namespace Tmf\WordPressExtension\Context\Initializer;

use Behat\Behat\Context\Context,
    Behat\Behat\Context\Initializer\ContextInitializer;

use Symfony\Component\Finder\Finder,
    Symfony\Component\Filesystem\Filesystem;

use Tmf\WordPressExtension\Context\WordPressContext;

/**
 * Class FeatureListener
 *
 * @package Tmf\WordPressExtension\Listener
 */
class WordPressContextInitializer implements ContextInitializer
{
    private $wordpressParams;
    private $minkParams;

    /**
     * inject the wordpress extension parameters and the mink parameters
     *
     * @param array $wordpressParams
     * @param array $minkParams
     */
    public function __construct($wordpressParams, $minkParams)
    {
        $this->wordpressParams = $wordpressParams;
        $this->minkParams = $minkParams;
    }

    /**
     * setup the wordpress environment / stack if the context is a wordpress context
     *
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if (!$context instanceof WordPressContext) {
            return;
        }
        $this->prepareEnvironment();
        $this->installFileFixtures();
        $this->loadStack();
    }

    /**
     * prepare environment variables
     */
    private function prepareEnvironment()
    {
        // wordpress uses these superglobal fields everywhere...
        $_SERVER['HTTP_HOST'] = parse_url($this->minkParams['base_url'], PHP_URL_HOST);
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';


        // we don't have a request uri in headless scenarios:
        // wordpress will try to "fix" php_self variable based on the request uri, if not present
        $PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';
    }

    /**
     * actually load the wordpress stack
     */
    private function loadStack()
    {
        // prevent wordpress from calling home to api.wordpress.org
        if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
            define('WP_INSTALLING', true);
        }

        $finder = new Finder();

        // load the wordpress "stack"
        $finder->files()->in($this->wordpressParams['path'])->depth('== 0')->name('wp-load.php');

        foreach ($finder as $bootstrapFile) {
            require_once $bootstrapFile->getRealpath();
        }
    }

    /**
     * create a wp-config.php and link plugins / themes
     */
    public function installFileFixtures()
    {
        $finder = new Finder();
        $fs = new Filesystem();
        $finder->files()->in($this->wordpressParams['path'])->depth('== 0')->name('wp-config-sample.php');
        foreach ($finder as $file) {
            $configContent =
                str_replace(array(
                    "'DB_NAME', 'database_name_here'",
                    "'DB_USER', 'username_here'",
                    "'DB_PASSWORD', 'password_here'"
                ), array(
                    sprintf("'DB_NAME', '%s'", $this->wordpressParams['connection']['db']),
                    sprintf("'DB_USER', '%s'", $this->wordpressParams['connection']['username']),
                    sprintf("'DB_PASSWORD', '%s'", $this->wordpressParams['connection']['password']),
                ), $file->getContents());
            $fs->dumpFile($file->getPath() . '/wp-config.php', $configContent);
        }
    }
}