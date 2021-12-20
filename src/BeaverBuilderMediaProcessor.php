<?php

namespace Smartling\BeaverBuilder;

use Smartling\Bootstrap;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorInterface;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class BeaverBuilderMediaProcessor implements MetaFieldProcessorInterface
{
    use LoggerSafeTrait;

    public function getFieldRegexp(): string
    {
        return BeaverBuilderFieldsFilterHelper::META_NODE_SETTINGS_NAME_REGEX . 'photos/\d+';
    }

    public function processFieldPostTranslation(SubmissionEntity $submission, $fieldName, $value)
    {
        return $value;
    }

    public function processFieldPreTranslation(SubmissionEntity $submission, $fieldName, $value, array $collectedFields)
    {
        $id = (int)$value;
        $post = get_post($id, \ARRAY_A);
        if (!is_array($post)) {
            $this->getLogger()->notice("Beaver Builder referenced photo id=$id, but no such post in blog");
            return $value;
        }
        $type = $post['post_type'];
        /**
         * @var SubmissionManager $submissionManager
         */
        $submissionManager = Bootstrap::getContainer()->get('manager.submission');
        /**
         * @var TranslationHelper $translationHelper
         */
        $translationHelper = Bootstrap::getContainer()->get('translation.helper');

        $targetSubmission = $translationHelper->prepareSubmission($type, $submission->getSourceBlogId(), $id, $submission->getTargetBlogId());
        if ($targetSubmission->getTargetId() === 0) {
            /**
             * @var SiteHelper $siteHelper
             */
            $siteHelper = Bootstrap::getContainer()->get('site.helper');
            $targetId = $siteHelper->withBlog($submission->getTargetBlogId(), function () use ($post) {
                $post['ID'] = '';
                return wp_insert_post($post);
            });
            $targetSubmission->setTargetId($targetId);
            $targetSubmission->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
            $submissionManager->storeEntity($targetSubmission);
        }

        return $targetSubmission->getTargetId();
    }
}
