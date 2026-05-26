<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_translations
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Translations\Administrator\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_translations.
 *
 * @since  1.0.0
 */
class TranslationsComponent extends MVCComponent implements BootableExtensionInterface
{
    /**
     * Booting the extension.
     *
     * @param   ContainerInterface  $container  The container
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function boot(ContainerInterface $container)
    {
    }

    /**
     * Returns valid contexts.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public function getContexts(): array
    {
        return ['com_translations.queueitem', 'com_translations.rule', 'com_translations.feedback'];
    }
}
