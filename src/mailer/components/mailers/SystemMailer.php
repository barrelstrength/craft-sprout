<?php

namespace BarrelStrength\Sprout\mailer\components\mailers;

use BarrelStrength\Sprout\mailer\components\elements\email\conditions\EmailCondition;
use BarrelStrength\Sprout\mailer\components\elements\email\EmailElement;
use BarrelStrength\Sprout\mailer\components\mailers\fieldlayoutelements\AudienceField;
use BarrelStrength\Sprout\mailer\components\mailers\fieldlayoutelements\ReplyToField;
use BarrelStrength\Sprout\mailer\components\mailers\fieldlayoutelements\SenderField;
use BarrelStrength\Sprout\mailer\components\mailers\fieldlayoutelements\ToField;
use BarrelStrength\Sprout\mailer\mailers\Mailer;
use BarrelStrength\Sprout\mailer\mailers\MailerInstructionsInterface;
use BarrelStrength\Sprout\mailer\mailers\MailerSendTestInterface;
use Craft;
use craft\elements\Asset;
use craft\fieldlayoutelements\HorizontalRule;
use craft\fs\Local;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\mail\Message;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use Exception;
use yii\base\ErrorException;
use yii\mail\MessageInterface;

class SystemMailer extends Mailer implements MailerSendTestInterface
{
    public ?array $approvedSenders = null;

    public ?array $approvedReplyToEmails = null;

    protected array $_attachmentExternalFilePaths = [];

    public static function displayName(): string
    {
        return 'System Mailer';
    }

    public function getName(): string
    {
        return 'System Mailer';
    }

    public function getDescription(): string
    {
        return Craft::t('sprout-module-mailer', 'Smart transactional email, easy recipient management, and advanced third party integrations.');
    }

    public static function getTabs(FieldLayout $fieldLayout): array
    {
        $campaignPackageCondition = [
            'class' => EmailCondition::class,
            'conditionRules' => [
                //                new PackageConditionRule([
                //                    'value' => self::class,
                //                ]),
            ],
        ];

        $audienceField = new AudienceField([
            'uid' => 'SPROUT-UID-RECIPIENTS-FIELD',
        ]);
        $audienceField->setElementCondition($campaignPackageCondition);

        $mailerTab = new FieldLayoutTab();
        $mailerTab->layout = $fieldLayout;
        $mailerTab->name = Craft::t('sprout-module-mailer', 'Mailer');
        $mailerTab->sortOrder = 0;
        $mailerTab->uid = 'SPROUT-UID-EMAIL-AUDIENCE-TAB';
        $mailerTab->setElements([
            new SenderField(),
            new ReplyToField(),
            new HorizontalRule(),
            new ToField(),
            $audienceField,
        ]);

        return [$mailerTab];
    }

    public function getSettingsHtml(): ?string
    {
        $html = Craft::$app->getView()->renderTemplate('sprout-module-mailer/_components/mailers/SystemMailer/settings', [
            'settings' => $this,
        ]);

        return $html;
    }

    public function createMailerInstructionsSettingsModel(): SystemMailerInstructionsSettings
    {
        return new SystemMailerInstructionsSettings();
    }

    public function createMailerInstructionsTestSettingsModel(): SystemMailerInstructionsTestSettings
    {
        return new SystemMailerInstructionsTestSettings();
    }

    public function getSendTestModalHtml(EmailElement $email = null): string
    {
        return Craft::$app->getView()->renderTemplate('sprout-module-mailer/_components/mailers/SystemMailer/send-test-fields', [
            'email' => $email,
            'mailer' => $this,
        ]);
    }

    public function send(EmailElement $email, MailerInstructionsInterface $mailerInstructionsSettings): void
    {
        $mailerInstructionsSettings->beforeSend($email);

        $templateVariables = $mailerInstructionsSettings->getAdditionalTemplateVariables($email);
        $email->getEmailTheme()->addTemplateVariables($templateVariables);

        $mailingList = $mailerInstructionsSettings->getMailingList($email, $templateVariables);

        // Prepare the Message
        $message = new Message();

        $assets = $mailerInstructionsSettings->getMessageFileAttachments($email);
        $this->attachFilesToMessage($message, $assets);

        $message->setFrom($mailerInstructionsSettings->getSender());
        $message->setReplyTo($mailerInstructionsSettings->getReplyToEmail());

        // If we have errors before we start processing recipients, throw an error
        if ($email->hasErrors()) {
            throw new ErrorException('Email has errors');
        }

        foreach ($mailingList->getRecipients() as $recipient) {

            try {
                $message->setTo($recipient->getSender());

                $this->_buildMessage($message, $email, $recipient, $mailerInstructionsSettings);

                if ($email->hasErrors() || $recipient->hasErrors()) {
                    $mailingList->markAsFailed($recipient);
                    continue;
                }

                if ($message->send()) {
                    $mailingList->markAsProcessed($recipient);
                } else {
                    $mailingList->markAsFailed($recipient);
                    continue;
                }
            } catch (Exception $e) {
                $recipient->addError($e->getMessage());
                $mailingList->markAsFailed($recipient);
            }
        }

        $mailingList->validate();

        if ($mailingList->hasErrors()) {
            $email->addError('recipients', $mailingList->getErrors());
            Craft::error(sprintf(
                'Email recipient errors for Email ID %s: %s',
                $email->id,
                UrlHelper::cpUrl($email->getCpEditUrl())
            ));
            Craft::error($email->getErrors());
        }

        $this->deleteExternalFilePaths($this->_attachmentExternalFilePaths);
    }

    protected function _buildMessage(
        MessageInterface            $message,
        EmailElement                $email,
        MailingListRecipient        $recipient,
        MailerInstructionsInterface $mailerInstructionsSettings,
    ): void {

        $view = Craft::$app->getView();
        $emailTheme = $email->getEmailTheme();

        $emailTheme->addTemplateVariable('email', $email);
        $emailTheme->addTemplateVariable('recipient', $recipient);

        $templateVariables = $emailTheme->getTemplateVariables();

        $subjectLine = $mailerInstructionsSettings->getSubjectLine($email);
        $subjectLine = $view->renderObjectTemplate($subjectLine, $templateVariables);
        $defaultBody = $view->renderObjectTemplate($email->defaultBody, $templateVariables);

        $textBody = '';
        $htmlBody = '';

        $textBody = trim($emailTheme->getTextBody());
        $htmlBody = trim($emailTheme->getHtmlBody());

        if (empty($textBody)) {
            throw new \yii\base\Exception('Text template is blank.');
        }

        if (empty($htmlBody)) {
            throw new \yii\base\Exception('HTML template is blank.');
        }

        $message->setSubject($subjectLine);
        $message->setTextBody($textBody);
        $message->setHtmlBody($htmlBody);
    }

    /**
     * @param Asset[] $assets
     */
    protected function attachFilesToMessage(MessageInterface $message, array $assets): void
    {
        foreach ($assets as $asset) {

            $name = $asset->getFilename();
            $volume = $asset->getVolume();

            if ($volume instanceof Local) {
                $path = $this->getLocalAssetFilePath($asset);
            } else {
                // External Asset sources
                $path = $asset->getCopyOfFile();
                // let's save the path to delete it after sent
                $this->_attachmentExternalFilePaths[] = $path;
            }

            if ($path) {
                $message->attach($path, ['fileName' => $name]);
            }
        }
    }

    protected function getLocalAssetFilePath(Asset $asset): string
    {
        /**
         * @var $volume Local
         */
        $volume = $asset->getVolume();

        $path = $volume->getRootPath() . DIRECTORY_SEPARATOR . $asset->getPath();

        return FileHelper::normalizePath($path);
    }

    protected function deleteExternalFilePaths($externalFilePaths): void
    {
        foreach ($externalFilePaths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
