<?php

use APP\core\Application;
use APP\core\Request as PKPRequest;
use APP\plugins\generic\dataverse\api\v1\datasets\DatasetHandler;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\dataverseStudy\Repository;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\submission\Submission;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\security\RoleDAO;
use PKP\tests\PKPTestCase;
use PKP\user\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class DatasetHandlerTest extends PKPTestCase
{
    protected function getMockedDAOs(): array
    {
        return ['RoleDAO'];
    }

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
        Locale::registerPath(dirname(__DIR__, 4) . '/locale');
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
        $context->setId(3);
        $context->setData('path', 'test');
        $context->setData('supportedSubmissionLocales', ['en']);
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('url')->willReturn('https://example.org/api/v1/vocabs');
        $pkpRequest = $this->createMock(PKPRequest::class);
        $pkpRequest->method('getContext')->willReturn($context);
        $pkpRequest->method('getDispatcher')->willReturn($dispatcher);
        $user = new User();
        $user->setId(5);
        $pkpRequest->method('getUser')->willReturn($user);
        Registry::set('request', $pkpRequest);
        $roleDao = $this->createMock(RoleDAO::class);
        $roleDao->method('userHasRole')->willReturn(true);
        DAORegistry::registerDAO('RoleDAO', $roleDao);

        $params = [
            'datasetTitle' => 'Updated title',
            'datasetDescription' => 'Updated description',
            'datasetKeywords' => [Locale::getLocale() => ['Updated keyword']],
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
        ] + $additionalMetadata);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())->method('getParsedBody')->willReturn($params);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $args = ['studyId' => 7];
        $handler = $this->getMockBuilder(DatasetHandler::class)
            ->disableOriginalConstructor()->onlyMethods(['get', 'getAuthorizedContextObject'])->getMock();
        $handler->method('get')->with($request, $response, $args)->willReturn($response);
        $handler->method('getAuthorizedContextObject')
            ->with(Application::ASSOC_TYPE_SUBMISSION)->willReturn($submission);

        $this->assertSame(200, $handler->edit($request, $response, $args)->getStatusCode());
    }
}
