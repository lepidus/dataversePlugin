<?php

use APP\core\Application;
use APP\core\Request;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\entities\DatasetIdentifier;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\exception\UploadNotSentException;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetFileActions;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\tests\DatabaseTestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class DatasetServiceDepositRecoveryTest extends DatabaseTestCase
{
    public static function reportingFailures(): array
    {
        return [[false], [true]];
    }

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    /** @dataProvider reportingFailures */
    public function testIngestFailureReportsTheFileErrorAndKeepsTheDepositRetryable(bool $reportingFails): void
    {
        ini_set('error_log', '/dev/null');
        Locale::registerPath(dirname(__DIR__, 2) . '/locale');
        DB::beginTransaction();
        try {
            $context = Application::getContextDAO()->getById(1);
            $submission = new Submission();
            $submission->setData('contextId', $context->getId());
            $id = Repo::submission()->add($submission, new Publication(), $context);
            $submission = Repo::submission()->get($id);
            $request = $this->createMock(Request::class);
            $request->method('getContext')->willReturn($context);
            $request->method('getUser')->willReturn(Repo::user()->get(1));
            Registry::set('request', $request);
            $configuration = new DataverseConfiguration();
            $configuration->setDataverseUrl('https://example.test/dataverse/test');
            $configurationDao = $this->getMockBuilder(DataverseConfigurationDAO::class)
                ->disableOriginalConstructor()->onlyMethods(['get'])->getMock();
            $configurationDao->method('get')->willReturn($configuration);
            DAORegistry::registerDAO('DataverseConfigurationDAO', $configurationDao);
            $identifier = new DatasetIdentifier();
            $identifier->setPersistentId('doi:10.1234/ABC');
            $actions = $this->getMockBuilder(DatasetActions::class)
                ->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
            $actions->method('create')->willReturn($identifier);
            $files = $this->getMockBuilder(DatasetFileActions::class)
                ->disableOriginalConstructor()->onlyMethods(['add'])->getMock();
            $files->method('add')->willThrowException(
                new UploadNotSentException('data.csv', new DataverseException('Ingest timeout'))
            );
            $client = Mockery::mock('overload:APP\\plugins\\generic\\dataverse\\dataverseAPI\\DataverseClient');
            $client->shouldReceive('getDatasetActions')->andReturn($actions);
            $client->shouldReceive('getDatasetFileActions')->andReturn($files);
            $file = new DatasetFile();
            $file->setId(7);
            $file->setOriginalFileName('data.csv');
            $file->setPath(__FILE__);
            $dataset = new Dataset();
            $dataset->setFiles([$file]);

            $service = new DatasetService();
            if ($reportingFails) {
                $service = $this->getMockBuilder(DatasetService::class)
                    ->onlyMethods(['registerAndNotifyError'])->getMock();
                $service->method('registerAndNotifyError')
                    ->willThrowException(new RuntimeException('Reporting unavailable'));
            }
            $result = $service->deposit($submission, $dataset);

            $this->assertSame('plugins.generic.dataverse.error.datasetFileDeposit', $result['message']);
            $this->assertSame('ready', DB::table('dataverse_deposits')->where('submission_id', $id)->value('state'));
            $this->assertSame($reportingFails ? 0 : 1, DB::table('event_log')->where('assoc_id', $id)
                ->where('message', 'plugins.generic.dataverse.error.datasetFileDeposit')->count());
        } finally {
            DB::rollBack();
        }
    }
}
