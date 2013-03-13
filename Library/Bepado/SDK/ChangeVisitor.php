<?php
/**
 * This file is part of the Bepado SDK Component.
 *
 * @version 1.0.0snapshot201303061109
 */

namespace Bepado\SDK;

/**
 * Base class for change visitor implementations
 *
 * @version 1.0.0snapshot201303061109
 */
abstract class ChangeVisitor
{
    /**
     * Visit changes
     *
     * @param array $changes
     * @return array
     */
    abstract public function visit(array $changes);
}
