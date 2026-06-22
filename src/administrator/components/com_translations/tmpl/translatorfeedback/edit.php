<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_translations
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/** @var \Joomla\Component\Translations\Administrator\View\Translatorfeedback\HtmlView $this */

$sourceArticle      = $this->item->source_article;
$translationArticle = $this->item->translation_article;
$hasTranslation     = $translationArticle !== null;
$contentId          = (int) $this->item->content_id;
$targetLanguage     = (string) $this->item->target_language;

// Render a read-only original body, falling back to a muted placeholder when the source field is empty.
$originalBody = function ($html) {
    if (trim((string) $html) === '') {
        return '<span class="text-muted fst-italic">' . Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_FIELD_EMPTY') . '</span>';
    }

    return $html;
};

// Render a read-only original plaintext value, falling back to a muted placeholder when empty.
$originalText = function ($value) {
    if (trim((string) $value) === '') {
        return '<span class="text-muted fst-italic">' . Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_FIELD_EMPTY') . '</span>';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$form = $this->form;

// Render one paired field row: the read-only source value beside its editable translation.
// $sourceClass lets a multi-line field (such as the meta description) match its taller translation input.
$fieldRow = function (string $field, $sourceValue, string $sourceClass = '') use ($form, $originalText, $hasTranslation) {
    ?>
    <div class="mb-4">
        <label class="fw-bold d-block mb-2"><?php echo Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_FIELD_' . strtoupper($field)); ?></label>
        <div class="row">
            <div class="col-lg-6">
                <div class="form-control-plaintext border rounded bg-body-tertiary px-3 py-2<?php echo $sourceClass !== '' ? ' ' . $sourceClass : ''; ?>"><?php echo $originalText($sourceValue); ?></div>
            </div>
            <div class="col-lg-6">
                <?php if ($hasTranslation) : ?>
                    <?php echo $form->getInput('translation_' . $field); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
};

$action = 'index.php?option=com_translations&view=translatorfeedback&layout=edit&id=' . $contentId . '&target=' . urlencode($targetLanguage);
?>

<form action="<?php echo Route::_($action); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">

    <div class="mb-3">
        <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_translations&view=queue'); ?>">
            <span class="icon-arrow-left" aria-hidden="true"></span>
            <?php echo Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_BACK_TO_QUEUE'); ?>
        </a>
    </div>

    <?php if ($sourceArticle === null) : ?>
        <div class="alert alert-warning">
            <span class="icon-warning" aria-hidden="true"></span>
            <?php echo Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_NO_SOURCE'); ?>
        </div>
    <?php else : ?>
        <?php // Column headers: left = original (read-only), right = translation (editable). ?>
        <div class="row mb-3">
            <div class="col-lg-6">
                <span class="fw-bold"><?php echo Text::sprintf('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_SOURCE_HEADING', $this->escape($sourceArticle->language)); ?></span>
                <span class="badge bg-secondary">
                    <span class="icon-lock" aria-hidden="true"></span>
                    <?php echo Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_READ_ONLY'); ?>
                </span>
            </div>
            <div class="col-lg-6">
                <span class="fw-bold"><?php echo Text::sprintf('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_TRANSLATION_HEADING', $this->escape($targetLanguage)); ?></span>
            </div>
        </div>

        <?php if (!$hasTranslation) : ?>
            <div class="alert alert-warning">
                <span class="icon-warning" aria-hidden="true"></span>
                <?php echo Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_NO_TRANSLATION'); ?>
            </div>
        <?php endif; ?>

        <?php // Content: title and the article bodies. ?>
        <h2 class="translations-section-heading"><?php echo Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_SECTION_CONTENT'); ?></h2>

        <?php $fieldRow('title', $sourceArticle->title); ?>

        <div class="mb-4">
            <div class="row">
                <div class="col-lg-6">
                    <?php // Original shown as a card so Atum paints its surface and header label in both light and dark; the header doubles as the field label and aligns the body with the editor. ?>
                    <div class="card border translations-readonly">
                        <div class="card-header fw-bold">
                            <span class="icon-lock opacity-75 me-2" aria-hidden="true"></span><?php echo Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_FIELD_INTROTEXT'); ?>
                        </div>
                        <?php // Article body is trusted, author-supplied HTML (rendered as com_content does). ?>
                        <div class="card-body bg-body-tertiary translations-readonly-body<?php echo trim((string) $sourceArticle->introtext) === '' ? ' translations-readonly-empty' : ''; ?>"><?php echo $originalBody($sourceArticle->introtext); ?></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <?php if ($hasTranslation) : ?>
                        <?php echo $form->getInput('translation_introtext'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <div class="row">
                <div class="col-lg-6">
                    <?php // Original shown as a card so Atum paints its surface and header label in both light and dark; the header doubles as the field label and aligns the body with the editor. ?>
                    <div class="card border translations-readonly">
                        <div class="card-header fw-bold">
                            <span class="icon-lock opacity-75 me-2" aria-hidden="true"></span><?php echo Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_FIELD_FULLTEXT'); ?>
                        </div>
                        <div class="card-body bg-body-tertiary translations-readonly-body<?php echo trim((string) $sourceArticle->fulltext) === '' ? ' translations-readonly-empty' : ''; ?>"><?php echo $originalBody($sourceArticle->fulltext); ?></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <?php if ($hasTranslation) : ?>
                        <?php echo $form->getInput('translation_fulltext'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php // Metadata: meta description and keywords use a taller source box to match their textareas. ?>
        <h2 class="translations-section-heading"><?php echo Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_SECTION_METADATA'); ?></h2>

        <?php $fieldRow('metadesc', $sourceArticle->metadesc, 'translations-readonly-multiline'); ?>
        <?php $fieldRow('metakey', $sourceArticle->metakey, 'translations-readonly-multiline'); ?>
        <?php $fieldRow('note', $sourceArticle->note); ?>

        <?php
        // Images: the alt and caption fields live in the images JSON column, and only matter
        // when the source actually has the matching image.
        $sourceImages     = new Registry($sourceArticle->images ?? '');
        $hasIntroImage    = trim((string) $sourceImages->get('image_intro', '')) !== '';
        $hasFulltextImage = trim((string) $sourceImages->get('image_fulltext', '')) !== '';
        ?>

        <?php if ($hasIntroImage || $hasFulltextImage) : ?>
            <h2 class="translations-section-heading"><?php echo Text::_('COM_TRANSLATIONS_TRANSLATOR_FEEDBACK_SECTION_IMAGES'); ?></h2>

            <?php if ($hasIntroImage) : ?>
                <?php $fieldRow('image_intro_alt', $sourceImages->get('image_intro_alt', '')); ?>
                <?php $fieldRow('image_intro_caption', $sourceImages->get('image_intro_caption', '')); ?>
            <?php endif; ?>

            <?php if ($hasFulltextImage) : ?>
                <?php $fieldRow('image_fulltext_alt', $sourceImages->get('image_fulltext_alt', '')); ?>
                <?php $fieldRow('image_fulltext_caption', $sourceImages->get('image_fulltext_caption', '')); ?>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="id" value="<?php echo $contentId; ?>">
    <input type="hidden" name="target" value="<?php echo $this->escape($targetLanguage); ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
