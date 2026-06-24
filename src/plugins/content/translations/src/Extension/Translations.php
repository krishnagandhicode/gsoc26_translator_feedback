<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.translations
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Content\Translations\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Model\AfterChangeStateEvent;
use Joomla\CMS\Event\Model\AfterDeleteEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Event\Model\BeforeDeleteEvent;
use Joomla\CMS\Event\Model\PrepareDataEvent;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

/**
 * Content plugin for the Translations component. Adds the "no need for translation"
 * toggle to the article form, and cascades a source article's trash or delete to its
 * translated items and the queue rows that track them.
 *
 * @since  0.3.0
 */
final class Translations extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Load the plugin language files automatically.
     *
     * @var    boolean
     * @since  0.3.0
     */
    protected $autoloadLanguage = true;

    /**
     * Content type stored in #__translations_queue for articles (matches the component).
     *
     * @var    string
     * @since  0.3.0
     */
    private const CONTENT_TYPE = 'com_content.article';

    /**
     * The form field this plugin adds to the article form.
     *
     * @var    string
     * @since  0.3.0
     */
    private const FIELD = 'no_need_for_translation';

    /**
     * The #__associations context that links an article's language versions.
     *
     * @var    string
     * @since  0.4.0
     */
    private const ASSOCIATIONS_CONTEXT = 'com_content.item';

    /**
     * The component booted to trash or delete an article's translations.
     *
     * @var    string
     * @since  0.4.0
     */
    private const COMPONENT = 'com_content';

    /**
     * The admin model used to trash or delete an article's translations.
     *
     * @var    string
     * @since  0.4.0
     */
    private const MODEL = 'Article';

    /**
     * Translated item ids captured per source id during onContentBeforeDelete, before
     * core removes the association link, so onContentAfterDelete can delete them.
     *
     * @var    array<int, int[]>
     * @since  0.4.0
     */
    private array $capturedTranslations = [];

    /**
     * Returns the events this subscriber listens to.
     *
     * @return  array
     *
     * @since   0.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepareData'  => 'onContentPrepareData',
            'onContentPrepareForm'  => 'onContentPrepareForm',
            'onContentAfterSave'    => 'onContentAfterSave',
            'onContentChangeState'  => 'onContentChangeState',
            'onContentBeforeDelete' => 'onContentBeforeDelete',
            'onContentAfterDelete'  => 'onContentAfterDelete',
        ];
    }

    /**
     * Add the "no need for translation" toggle to the source-language article form.
     *
     * @param   PrepareFormEvent  $event  The event.
     *
     * @return  void
     *
     * @since   0.3.0
     */
    public function onContentPrepareForm(PrepareFormEvent $event): void
    {
        $form = $event->getForm();

        if ($form->getName() !== 'com_content.article') {
            return;
        }

        // Load the toggle for source-language articles and new ones (language not set yet).
        $data     = $event->getData();
        $language = (string) (\is_object($data) ? ($data->language ?? '') : ($data['language'] ?? ''));

        if ($language !== '' && $language !== $this->getSourceLanguage()) {
            return;
        }

        $form->loadFile(\dirname(__DIR__, 2) . '/forms/translationoptout.xml');
    }

    /**
     * Default a new article's language to the source language, and reflect the queue flag on the toggle.
     *
     * A new article has no language yet, so it is set to the source language here. For an existing article
     * the displayed toggle value is set from the queue before the form binds the stored attribs (the form
     * binds after onContentPrepareForm runs), so it survives even after the flag was cleared from the grid.
     *
     * @param   PrepareDataEvent  $event  The event.
     *
     * @return  void
     *
     * @since   0.3.0
     */
    public function onContentPrepareData(PrepareDataEvent $event): void
    {
        if ($event->getContext() !== 'com_content.article') {
            return;
        }

        $data = $event->getData();

        if (!\is_object($data)) {
            return;
        }

        // A new article has no language yet; default it to the source language.
        if (empty($data->id) && (string) ($data->language ?? '') === '') {
            $data->language = $this->getSourceLanguage();

            return;
        }

        // The edit form supplies the item with attribs as an array; leave other shapes alone.
        if (empty($data->id) || !\is_array($data->attribs ?? null)) {
            return;
        }

        $data->attribs[self::FIELD] = $this->isFlagged((int) $data->id) ? 1 : 0;
    }

    /**
     * Persist the toggle to the article's queue row when a source-language article is saved.
     *
     * @param   AfterSaveEvent  $event  The event.
     *
     * @return  void
     *
     * @since   0.3.0
     */
    public function onContentAfterSave(AfterSaveEvent $event): void
    {
        if ($event->getContext() !== 'com_content.article') {
            return;
        }

        $article = $event->getItem();

        // Only source-language originals are tracked in the queue.
        if ((string) ($article->language ?? '') !== $this->getSourceLanguage()) {
            return;
        }

        // The toggle lives in the article's attribs group, so it arrives nested under 'attribs'.
        $data           = $event->getData();
        $attribs        = $data['attribs'] ?? [];
        $doNotTranslate = (int) (\is_array($attribs) ? ($attribs[self::FIELD] ?? 0) : 0);

        $this->storeFlag((int) $article->id, $doNotTranslate);
    }

    /**
     * Trash an article's translations when the source article is trashed.
     *
     * @param   AfterChangeStateEvent  $event  The event.
     *
     * @return  void
     *
     * @since   0.4.0
     */
    public function onContentChangeState(AfterChangeStateEvent $event): void
    {
        // -2 is the trashed state; publish, unpublish and archive do not cascade.
        if ($event->getContext() !== self::CONTENT_TYPE || $event->getValue() !== -2) {
            return;
        }

        foreach ($event->getPks() as $pk) {
            $sourceId = (int) $pk;

            // Only sources this component manages cascade.
            if ($this->queueId($sourceId) === null) {
                continue;
            }

            $translations = $this->translationGroupIds($sourceId);

            if ($translations !== []) {
                $this->trashTranslations($translations);
            }
        }
    }

    /**
     * Capture a managed source's translation group before its delete removes the link.
     *
     * @param   BeforeDeleteEvent  $event  The event.
     *
     * @return  void
     *
     * @since   0.4.0
     */
    public function onContentBeforeDelete(BeforeDeleteEvent $event): void
    {
        if ($event->getContext() !== self::CONTENT_TYPE) {
            return;
        }

        $sourceId = (int) $event->getItem()->id;

        // Core removes the association link during the delete that follows, so the group
        // is read now; an empty array still marks a managed source whose queue row to clean.
        if ($this->queueId($sourceId) === null) {
            return;
        }

        $this->capturedTranslations[$sourceId] = $this->translationGroupIds($sourceId);
    }

    /**
     * Delete a deleted source's translations and clean up its queue rows.
     *
     * @param   AfterDeleteEvent  $event  The event.
     *
     * @return  void
     *
     * @since   0.4.0
     */
    public function onContentAfterDelete(AfterDeleteEvent $event): void
    {
        if ($event->getContext() !== self::CONTENT_TYPE) {
            return;
        }

        $sourceId = (int) $event->getItem()->id;

        if (!isset($this->capturedTranslations[$sourceId])) {
            return;
        }

        $translations = $this->capturedTranslations[$sourceId];
        unset($this->capturedTranslations[$sourceId]);

        // The translations were trashed with the source, so they can now be deleted.
        if ($translations !== []) {
            $this->deleteTranslations($translations);
        }

        $this->removeFromQueue($sourceId);
    }

    /**
     * The component's configured source language.
     *
     * @return  string
     *
     * @since   0.3.0
     */
    private function getSourceLanguage(): string
    {
        return (string) ComponentHelper::getParams('com_translations')->get('source_language', 'en-GB');
    }

    /**
     * Whether the article is currently flagged "no need for translation".
     *
     * @param   integer  $articleId  The article id.
     *
     * @return  boolean
     *
     * @since   0.3.0
     */
    private function isFlagged(int $articleId): bool
    {
        // Bound parameters are passed by reference, so the constant needs a variable.
        $contentType = self::CONTENT_TYPE;

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('do_not_translate'))
            ->from($db->quoteName('#__translations_queue'))
            ->where($db->quoteName('content_type') . ' = :contentType')
            ->where($db->quoteName('content_id') . ' = :contentId')
            ->bind(':contentType', $contentType, ParameterType::STRING)
            ->bind(':contentId', $articleId, ParameterType::INTEGER);
        $db->setQuery($query);

        return (bool) $db->loadResult();
    }

    /**
     * Store the flag on the article's queue row, creating the row only to record an opt-out.
     *
     * @param   integer  $articleId       The article id.
     * @param   integer  $doNotTranslate  1 to opt out of translation, 0 to allow it.
     *
     * @return  void
     *
     * @since   0.3.0
     */
    private function storeFlag(int $articleId, int $doNotTranslate): void
    {
        $db      = $this->getDatabase();
        $queueId = $this->queueId($articleId);

        if ($queueId !== null) {
            $row = (object) [
                'id'               => $queueId,
                'do_not_translate' => $doNotTranslate,
            ];
            $db->updateObject('#__translations_queue', $row, 'id');

            return;
        }

        // No row yet: create one only to record an opt-out, never just to store the default.
        if ($doNotTranslate === 0) {
            return;
        }

        $row = (object) [
            'content_type'     => self::CONTENT_TYPE,
            'content_id'       => $articleId,
            'do_not_translate' => 1,
        ];
        $db->insertObject('#__translations_queue', $row);
    }

    /**
     * The article's queue row id, or null when the article is not in the queue.
     *
     * @param   integer  $sourceId  The article id.
     *
     * @return  integer|null
     *
     * @since   0.4.0
     */
    private function queueId(int $sourceId): ?int
    {
        // Bound parameters are passed by reference, so the constant needs a variable.
        $contentType = self::CONTENT_TYPE;

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__translations_queue'))
            ->where($db->quoteName('content_type') . ' = :contentType')
            ->where($db->quoteName('content_id') . ' = :contentId')
            ->bind(':contentType', $contentType, ParameterType::STRING)
            ->bind(':contentId', $sourceId, ParameterType::INTEGER);
        $db->setQuery($query);

        $queueId = $db->loadResult();

        return $queueId === null ? null : (int) $queueId;
    }

    /**
     * The ids of an article's translations: its association group, minus itself.
     *
     * @param   integer  $sourceId  The source article id.
     *
     * @return  int[]
     *
     * @since   0.4.0
     */
    private function translationGroupIds(int $sourceId): array
    {
        // Bound parameters are passed by reference, so the constant needs a variable.
        $context = self::ASSOCIATIONS_CONTEXT;

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('groupAssociation.id'))
            ->from($db->quoteName('#__associations', 'sourceAssociation'))
            ->join(
                'INNER',
                $db->quoteName('#__associations', 'groupAssociation'),
                $db->quoteName('groupAssociation.key') . ' = ' . $db->quoteName('sourceAssociation.key')
            )
            ->where($db->quoteName('sourceAssociation.context') . ' = :context')
            ->where($db->quoteName('groupAssociation.context') . ' = :groupContext')
            ->where($db->quoteName('sourceAssociation.id') . ' = :sourceId')
            ->bind(':context', $context, ParameterType::STRING)
            ->bind(':groupContext', $context, ParameterType::STRING)
            ->bind(':sourceId', $sourceId, ParameterType::INTEGER);
        $db->setQuery($query);

        $ids = [];

        foreach ($db->loadColumn() as $id) {
            if ((int) $id !== $sourceId) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * Trash the given articles through the managing component's model.
     *
     * @param   int[]  $ids  The article ids.
     *
     * @return  void
     *
     * @since   0.4.0
     */
    private function trashTranslations(array $ids): void
    {
        // publish() takes the ids by reference; -2 is the trashed state.
        $pks = $ids;
        $this->articleModel()->publish($pks, -2);
    }

    /**
     * Delete the given articles through the managing component's model.
     *
     * @param   int[]  $ids  The article ids.
     *
     * @return  void
     *
     * @since   0.4.0
     */
    private function deleteTranslations(array $ids): void
    {
        // delete() takes the ids by reference.
        $pks = $ids;
        $this->articleModel()->delete($pks);
    }

    /**
     * Boot the managing component and create its admin model.
     *
     * @return  AdminModel
     *
     * @since   0.4.0
     */
    private function articleModel(): AdminModel
    {
        /** @var CMSApplicationInterface $application */
        $application = $this->getApplication();

        /** @var ComponentInterface&MVCFactoryServiceInterface $component */
        $component = $application->bootComponent(self::COMPONENT);

        /** @var AdminModel $model */
        $model = $component->getMVCFactory()->createModel(self::MODEL, 'Administrator', ['ignore_request' => true]);

        return $model;
    }

    /**
     * Remove a source's queue row and its per-language state rows.
     *
     * @param   integer  $sourceId  The source article id.
     *
     * @return  void
     *
     * @since   0.4.0
     */
    private function removeFromQueue(int $sourceId): void
    {
        $queueId = $this->queueId($sourceId);

        if ($queueId === null) {
            return;
        }

        $db = $this->getDatabase();

        // The state rows have no delete cascade, so remove them before the queue row.
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__translations_queue_states'))
            ->where($db->quoteName('queue_id') . ' = :queueId')
            ->bind(':queueId', $queueId, ParameterType::INTEGER);
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__translations_queue'))
            ->where($db->quoteName('id') . ' = :queueId')
            ->bind(':queueId', $queueId, ParameterType::INTEGER);
        $db->setQuery($query);
        $db->execute();
    }
}
