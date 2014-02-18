<?php
/**
 * This file is part of the Bepado Common component.
 *
 * @version $Revision$
 */

namespace Bepado\Common\Rpc;

/**
 * Handle PHP errors and notices gracefully to allow
 */
abstract class ErrorHandler
{
    private $oldExceptionHandler;

    public function registerHandlers()
    {
        ini_set('display_errors', false);
        $this->oldExceptionHandler = set_exception_handler(array($this, 'handleException'));

        register_shutdown_function(array($this, 'handleShutdown'));
    }

    public function restore()
    {
        set_exception_handler($this->oldExceptionHandler);
        $this->oldErrorHandler = $this->oldExceptionHandler = null;
    }

    public function handleShutdown()
    {
        $error = error_get_last();

        if ($error === NULL) {
            return;
        }

        if ($error['type'] > 4) {
            return;
        }

        $error = "{$error['message']} in {$error['file']}+{$error['line']}";

        $this->renderResponse($error);
        exit(1);
    }

    public function handleException($e)
    {
        $this->renderResponse($e->getMessage());
        exit(1);
    }

    abstract public function renderResponse($message);
}
