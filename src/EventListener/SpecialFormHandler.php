<?php
namespace App\EventListener;

use App\Request\JsonRequestProcessor;
use App\Services\Form\FormRequestValidator as FormValidator;
use App\Services\Form\FormRequestPreparator;
use Sulu\Bundle\FormBundle\Configuration\FormConfigurationFactory;
use Sulu\Bundle\FormBundle\Entity\Dynamic;
use Sulu\Bundle\FormBundle\Event\RequestListener as BaseRequestListener;
use Sulu\Bundle\FormBundle\Form\BuilderInterface;
use Sulu\Bundle\FormBundle\Form\HandlerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class SpecialFormHandler extends BaseRequestListener
{
    protected $formBuilder;
    protected $formHandler;
    protected $eventDispatcher;
    protected $invalidSubmittedForm = false;
    private $formValidator;
    private $formPreparator;
    private $jsonProcessor;
    private $processed = false;
    private $errors = [];

    private $logger;

    public function __construct(
        BuilderInterface $formBuilder,
        HandlerInterface $formHandler,
        FormConfigurationFactory $formConfigurationFactory,
        EventDispatcherInterface $eventDispatcher,
        FormValidator $formValidator,
        FormRequestPreparator $formPreparator,
        JsonRequestProcessor $jsonProcessor,
        LoggerInterface $logger
    ) {
        parent::__construct($formBuilder, $formHandler, $formConfigurationFactory, $eventDispatcher);
        $this->formBuilder = $formBuilder;
        $this->formHandler = $formHandler;
        $this->eventDispatcher = $eventDispatcher;
        $this->formValidator = $formValidator;
        $this->formPreparator = $formPreparator;
        $this->jsonProcessor = $jsonProcessor;
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $this->logger->info('onKernelRequest start');
        $request = $event->getRequest();
        if (!$this->formValidator->isFormApiRoute($request)) {
            parent::onKernelRequest($event);
            return;
        }


        if (!$this->shouldProcess($request)) {
            $this->logger->info('shouldProcess');
            $request->attributes->set('_special_form', [
                'error' => "Your request is Unprocessable.",
                'form_id' => null
            ]);
            $this->processed = true;
            return;
        }

        if (!$this->jsonProcessor->isJsonRequest($request)) {
            $this->logger->info('isJsonRequest');

            $request->attributes->set('_special_form', [
                'error' => 'Only application/json content type is supported.',
                'form_id' => null,
                'error_code' => Response::HTTP_UNSUPPORTED_MEDIA_TYPE
            ]);
            $this->processed = true;
            return;
        }

        try {
            $request = $this->formPreparator->prepareFormRequest($request);
            $form = $this->formBuilder->buildByRequest($request);
            
            if (!$form) {
                $this->logger->info('buildByRequest start');
                 
                $request->attributes->set('_special_form', [
                    'error' => 'Form could not be built.',
                    'details' => 'Check formBuilder parameters and ensure all required fields are valid.',
                    'form_id' => null
                ]);
                $this->processed = true;
                return;
            }

            $formEntity = $form->getConfig()->getOption('formEntity');
            if (!$this->formValidator->isRegisteredForm($formEntity->getId())) {
                $this->logger->info('isRegisteredForm start', array('formId' => $formEntity->getId()));
                return;
            }

            if(!$form->isSubmitted()) {
                $this->logger->info('not Submitted', [
                    'method' => $request->getMethod(),
                    'is_post' => $request->isMethod('post')
                ]);
                
                $request->attributes->set('_special_form', [
                    'message' => 'Form not submitted',
                    'errors' => $this->getFormErrors($form),
                    'form_id' => $formEntity->getId()
                ]);
                $this->processed = true;
                return;
            }

            if (!$form->isValid()) {
                $this->logger->info('isValid start');
                $this->invalidSubmittedForm = true;
                $request->attributes->set('_special_form', [
                    'errors' => $this->getFormErrors($form),
                    'form_id' => $formEntity->getId()
                ]);
                $this->processed = true;
                return;
            }

            /** @var Dynamic $dynamic */
            $dynamic = $form->getData();
            $locale = $dynamic->getLocale() ?? 'en';
            $configuration = $this->formConfigurationFactory->buildByDynamic($dynamic);
            $dynamic->setLocale($locale);

            if ($this->formHandler->handle($form, $configuration)) {
                $this->logger->info('handle start');
                $translation = $formEntity->getTranslation($locale);
                $request->attributes->set('_special_form', [
                    'success' => true,
                    'message' => $translation->getSuccessText() ?? 'Thank you for your submission!',
                    'form_id' => $formEntity->getId()
                ]);
                $this->processed = true;
            }
        } catch (\Exception $e) {
            $request->attributes->set('_special_form', [
                'error' => $e->getMessage(),
                'form_id' => isset($formEntity) ? $formEntity->getId() : null
            ]);

            $this->processed = true;
        }
        $this->logger->info('onKernelRequest end');
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $this->logger->info('onKernelResponse', array('processed' =>$this->processed));

        if (!$this->processed) {
            return;
        }

        $request = $event->getRequest();
        $formData = $request->attributes->get('_special_form');
        $formType = isset($formData['form_id']) && !is_null($formData['form_id'])
            ? $this->formValidator->getFormType($formData['form_id'])
            : "Undefined";

        if (isset($formData['errors'])) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'errors' => $formData['errors'],
                'form_type' => $formType,
            ], Response::HTTP_BAD_REQUEST));
        } elseif (isset($formData['error'])) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'error' => $formData['error'],
                'form_type' => $formType,
            ], isset($formData['error_code']) ? $formData['error_code'] : Response::HTTP_INTERNAL_SERVER_ERROR));
        } elseif (isset($formData['success'])) {
            $event->setResponse(new JsonResponse([
                'success' => true,
                'message' => $formData['message'],
                'form_type' => $formType,
            ], Response::HTTP_CREATED));
        }
    }

    private function shouldProcess(Request $request): bool
    {
        return $request->isMethod('POST') && $this->formValidator->isSpecialFormRequest($request);
    }

    private function getFormErrors($form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $errors[$origin ? $origin->getName() : '_global'] = $error->getMessage();
        }
        return $errors;
    }
}