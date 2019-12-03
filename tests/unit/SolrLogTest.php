<?php


namespace Firesphere\SolrSearch\Tests;

use Firesphere\SolrSearch\Models\SolrLog;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\DefaultAdminService;

class SolrLogTest extends SapphireTest
{
    /**
     * @var SolrLog
     */
    protected $log;

    public function testCan()
    {
        $this->assertFalse($this->log->canCreate(null));
        $this->assertFalse($this->log->canEdit(null));
        // True because we're in dev mode
        $this->assertTrue($this->log->canDelete(null));
        /** @var DefaultAdminService $admin */
        $admin = singleton(DefaultAdminService::class);
        $admin = $admin->findOrCreateDefaultAdmin();
        $this->assertTrue($this->log->canView($admin));
        $this->assertTrue($this->log->canDelete($admin));
    }

    public function testGetLastErrorLine()
    {
        $error = SolrLog::create(['Message' => "Test\nNew\nLine", 'Level' => 'ERROR']);

        $this->assertEquals('Test', $error->getLastErrorLine());

        $error2 = SolrLog::create(['Message' => 'Testing', 'Level' => 'WARN']);

        $this->assertEquals('Testing', $error2->getLastErrorLine());
    }

    public function testProvidePermissions()
    {
        $expected = [
            'DELETE_LOG' => [
                'name'     => _t(self::class . '.PERMISSION_DELETE_DESCRIPTION', 'Delete Solr logs'),
                'category' => _t('Permissions.LOGS_CATEGORIES', 'Solr logs permissions'),
                'help'     => _t(
                    self::class . '.PERMISSION_DELETE_HELP',
                    'Permission required to delete existing Solr logs.'
                ),
            ],
            'VIEW_LOG'   => [
                'name'     => _t(self::class . '.PERMISSION_VIEW_DESCRIPTION', 'View Solr logs'),
                'category' => _t('Permissions.LOGS_CATEGORIES', 'Solr logs permissions'),
                'help'     => _t(
                    self::class . '.PERMISSION_VIEW_HELP',
                    'Permission required to view existing Solr logs.'
                ),
            ],
        ];

        $this->assertEquals($expected, $this->log->providePermissions());
    }

    protected function setUp()
    {
        $this->log = SolrLog::create();

        return parent::setUp();
    }
}
