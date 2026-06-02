<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_translations
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Translations\Administrator\View\Editor\HtmlView $this */

$sourceArticle      = $this->item->source_article;
$translationArticle = $this->item->translation_article;
?>

<div class="mb-3">
    <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_translations&view=queue'); ?>">
        <span class="icon-arrow-left" aria-hidden="true"></span>
        <?php echo Text::_('COM_TRANSLATIONS_EDITOR_BACK_TO_QUEUE'); ?>
    </a>
</div>

<?php if ($sourceArticle === null) : ?>
    <div class="alert alert-warning">
        <span class="icon-warning" aria-hidden="true"></span>
        <?php echo Text::_('COM_TRANSLATIONS_EDITOR_NO_SOURCE'); ?>
    </div>
<?php else : ?>
    <div class="row">
        <?php // Left: the source article, read-only. ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo Text::sprintf('COM_TRANSLATIONS_EDITOR_SOURCE_HEADING', $this->escape($sourceArticle->language)); ?></span>
                    <span class="badge bg-secondary">
                        <span class="icon-lock" aria-hidden="true"></span>
                        <?php echo Text::_('COM_TRANSLATIONS_EDITOR_READ_ONLY'); ?>
                    </span>
                </div>
                <div class="card-body">
                    <h3><?php echo $this->escape($sourceArticle->title); ?></h3>
                    <?php // Article body is trusted, author-supplied HTML (rendered as com_content does). ?>
                    <div class="translations-editor-source">
                        <?php echo $sourceArticle->introtext . $sourceArticle->fulltext; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php // Right: the translation - the editable surface. ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <?php echo Text::sprintf('COM_TRANSLATIONS_EDITOR_TRANSLATION_HEADING', $this->escape($this->item->target_language)); ?>
                </div>
                <div class="card-body">
                    <?php if ($translationArticle === null) : ?>
                        <div class="alert alert-warning">
                            <span class="icon-warning" aria-hidden="true"></span>
                            <?php echo Text::_('COM_TRANSLATIONS_EDITOR_NO_TRANSLATION'); ?>
                        </div>
                    <?php else : ?>
                        <?php // Stacked fields (label above input) so they don't crowd in the narrow column. ?>
                        <div class="mb-3">
                            <?php echo $this->form->getLabel('translation_title'); ?>
                            <?php echo $this->form->getInput('translation_title'); ?>
                        </div>
                        <div class="mb-3">
                            <?php echo $this->form->getLabel('translation_text'); ?>
                            <?php echo $this->form->getInput('translation_text'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
