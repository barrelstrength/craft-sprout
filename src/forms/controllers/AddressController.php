<?php

namespace BarrelStrength\Sprout\forms\controllers;

use BarrelStrength\Sprout\forms\fields\address\Address;
use BarrelStrength\Sprout\forms\fields\address\AddressFieldTrait;
use BarrelStrength\Sprout\forms\FormsModule;
use Craft;
use craft\helpers\Json;
use craft\web\Controller;
use Exception;
use yii\web\Response;

class AddressController extends Controller
{
    /**
     * @var  int|bool|array
     */
    protected int|bool|array $allowAnonymous = [
        'update-address-form-html',
    ];

    /**
     * Updates the Address Form Input HTML
     */
    public function actionUpdateAddressFormHtml(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $addressFormatter = FormsModule::getInstance()->addressFormatter;

        $addressId = Craft::$app->getRequest()->getBodyParam('addressId');
        $fieldId = Craft::$app->getRequest()->getBodyParam('fieldId');
        $countryCode = Craft::$app->getRequest()->getBodyParam('countryCode');
        $namespace = Craft::$app->getRequest()->getBodyParam('namespace') ?? 'address';
        $overrideTemplatePaths = Craft::$app->getRequest()->getBodyParam('overrideTemplatePaths', false);

        $oldTemplatePath = Craft::$app->getView()->getTemplatesPath();

        if ($overrideTemplatePaths) {
            $sproutFormsTemplatePath = Craft::$app->getSession()->get('sproutforms-templatepath-fields');
            Craft::$app->getView()->setTemplatesPath($sproutFormsTemplatePath);

            // Set the base path to blank to enable Sprout Forms and Template Overrides
            $addressFormatter->setBaseAddressFieldPath('');
        }

        $addressModel = new Address();
        $addressModel->id = $addressId;
        $addressModel->fieldId = $fieldId;

        $addressFormatter->setNamespace($namespace);
        $addressFormatter->setCountryCode($countryCode);
        $addressFormatter->setAddressModel($addressModel);

        $addressFormHtml = $addressFormatter->getAddressFormHtml();

        if ($overrideTemplatePaths) {
            // Set the base path to blank to enable Sprout Forms and Template Overrides
            $addressFormatter->setBaseAddressFieldPath('');

            // Set our template path back to what it was before our ajax request
            Craft::$app->getView()->setTemplatesPath($oldTemplatePath);
        }

        return $this->asJson([
            'html' => $addressFormHtml,
        ]);
    }

    public function actionGetAddressFormFieldsHtml(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $addressFormatter = FormsModule::getInstance()->addressFormatter;

        $addressId = Craft::$app->getRequest()->getBodyParam('addressId');
        $fieldId = Craft::$app->getRequest()->getBodyParam('fieldId');
        $defaultCountryCode = Craft::$app->getRequest()->getBodyParam('defaultCountryCode');

        // Integrations like Sprout SEO will not have an address ID
        if ($addressId) {
            $addressModel = FormsModule::getInstance()->addressField->getAddressById($addressId);
        } else {
            $addressModel = new Address();
        }

        $addressModel->countryCode = $defaultCountryCode;
        $addressModel->fieldId = $fieldId;
        $addressDisplayHtml = $addressFormatter->getAddressDisplayHtml($addressModel);

        return $this->asJson([
            'html' => $addressDisplayHtml,
        ]);
    }

    /**
     * Get an address
     */
    public function actionGetAddressDisplayHtml(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $addressFormatter = FormsModule::getInstance()->addressFormatter;

        $formValues = Craft::$app->getRequest()->getBodyParam('formValues');
        $namespace = Craft::$app->getRequest()->getBodyParam('namespace') ?? 'address';

        $addressId = $formValues['id'] ?? null;
        $addressModel = new Address($formValues);
        $addressModel->id = $addressId;

        if (!$addressModel->validate()) {
            return $this->asJson([
                'result' => false,
                'errors' => $addressModel->getErrors(),
            ]);
        }

        $addressDisplayHtml = $addressFormatter->getAddressDisplayHtml($addressModel);
        $countryCode = $addressModel->countryCode;

        $addressFormatter->setNamespace($namespace);

        /** @var AddressFieldTrait $field */
        if (!empty($addressModel->fieldId) && $field = Craft::$app->fields->getFieldById($addressModel->fieldId)) {
            $addressFormatter->setHighlightCountries($field->highlightCountries);
        }

        $addressFormatter->setCountryCode($countryCode);
        $addressFormatter->setAddressModel($addressModel);

        $countryCodeHtml = $addressFormatter->getCountryInputHtml();
        $addressFormHtml = $addressFormatter->getAddressFormHtml();

        return $this->asJson([
            'result' => true,
            'html' => $addressDisplayHtml,
            'countryCodeHtml' => $countryCodeHtml,
            'addressFormHtml' => $addressFormHtml,
            'countryCode' => $countryCode,
        ]);
    }

    /**
     * Returns the Geo Coordinates for an Address via the Google Maps service
     */
    public function actionQueryAddressCoordinatesFromGoogleMaps(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $addressInfo = null;

        if (Craft::$app->getRequest()->getBodyParam('addressInfo') != null) {
            $addressInfo = Craft::$app->getRequest()->getBodyParam('addressInfo');
        }

        $result = [
            'result' => false,
            'errors' => [],
        ];

        try {
            $data = [];

            if ($addressInfo) {
                $addressInfo = str_replace('\n', ' ', $addressInfo);

                // Get JSON results from this request
                $geo = file_get_contents('http://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($addressInfo) . '&sensor=false');

                // Convert the JSON to an array
                $geo = Json::decode($geo);

                if ($geo['status'] === 'OK') {
                    $data['latitude'] = $geo['results'][0]['geometry']['location']['lat'];
                    $data['longitude'] = $geo['results'][0]['geometry']['location']['lng'];

                    $result = [
                        'result' => true,
                        'errors' => [],
                        'geo' => $data,
                    ];
                }
            }
        } catch (Exception $exception) {
            $result['result'] = false;
            $result['errors'] = $exception->getMessage();
        }

        return $this->asJson($result);
    }
}
