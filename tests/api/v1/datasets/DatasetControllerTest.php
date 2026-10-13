<?php

use APP\core\Application;
use APP\core\Request as PKPRequest;
use APP\plugins\generic\dataverse\api\v1\datasets\DatasetController;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\dataverseStudy\Repository;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\facades\Locale;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DatasetControllerTest extends PKPTestCase
{
    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function getMockedContainerKeys(): array
    {
        return [Repository::class];
    }

    public function testEditForwardsAdditionalRequiredMetadataToTheUpdateService(): void
    {
        $submission = new Submission();
        $submission->setId(23);
        $study = new DataverseStudy();
        $study->setSubmissionId($submission->getId());
        $study->setPersistentId('doi:10.5072/FK2/TEST');

        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('get')->with(7)->andReturn($study);
        app()->instance(Repository::class, $repository);

        $additionalMetadata = [
            'alternativeURL' => 'https://example.org/updated',
            'dsDescriptionDate' => '2026-10-13',
            'PSRI1' => 'Updated first value',
            'PSRI2' => 'Updated second value',
        ];
        $fields = [];
        foreach ($additionalMetadata as $name => $value) {
            $fields[] = [
                'name' => $name,
                'displayName' => $name,
                'description' => '',
                'isRequired' => true,
                'type' => 'TEXT',
            ];
        }
        $collectionActions = $this->getMockBuilder(DataverseCollectionActions::class)
            ->disableOriginalConstructor()->onlyMethods(['getRequiredMetadata', 'getLicenses'])->getMock();
        $collectionActions->method('getRequiredMetadata')->willReturn(['citation' => ['fields' => $fields]]);
        $collectionActions->method('getLicenses')->willReturn([['name' => 'CC0 1.0']]);
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDataverseCollectionActions')->andReturn($collectionActions);

        $context = Application::getContextDAO()->newDataObject();
        $context->setData('path', 'test');
        $context->setData('supportedSubmissionLocales', ['en']);
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('url')->willReturn('https://example.org/api/v1/vocabs');
        $pkpRequest = $this->createMock(PKPRequest::class);
        $pkpRequest->method('getContext')->willReturn($context);
        $pkpRequest->method('getDispatcher')->willReturn($dispatcher);
        Registry::set('request', $pkpRequest);

        $params = [
            'datasetTitle' => 'Updated title',
            'datasetDescription' => 'Updated description',
            'datasetKeywords' => [Locale::getLocale() => [['name' => 'Updated keyword']]],
            'datasetLanguage' => 'English',
            'datasetSubject' => 'Other',
            'datasetLicense' => 'CC0 1.0',
            'datasetRelationType' => 'IsSupplementTo',
            'submissionId' => $submission->getId(),
            'datasetPersistentId' => 'doi:10.5072/FK2/UNTRUSTED',
            'datasetUnexpectedField' => 'Ignore this value',
        ];
        foreach ($additionalMetadata as $name => $value) {
            $params['dataset' . ucfirst($name)] = $value;
        }

        $service = Mockery::mock('overload:' . DatasetService::class);
        $service->shouldReceive('update')->once()->with([
            'persistentId' => $study->getPersistentId(),
            'title' => 'Updated title',
            'description' => 'Updated description',
            'keywords' => ['Updated keyword'],
            'language' => 'English',
            'subject' => 'Other',
            'license' => 'CC0 1.0',
            'relationType' => 'IsSupplementTo',
            ...$additionalMetadata,
        ]);

        $request = Request::create('/datasets/7', 'PUT', $params);
        $route = new Illuminate\Routing\Route('PUT', 'datasets/{studyId}', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $controller = $this->getMockBuilder(DatasetController::class)
            ->onlyMethods(['getAuthorizedContextObject', 'get'])
            ->getMock();
        $controller->method('getAuthorizedContextObject')
            ->with(Application::ASSOC_TYPE_SUBMISSION)->willReturn($submission);
        $controller->method('get')->with($request)->willReturn(new JsonResponse(['updated' => true]));

        $this->assertSame(200, $controller->edit($request)->getStatusCode());
    }
}
