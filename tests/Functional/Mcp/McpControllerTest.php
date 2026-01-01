<?php

declare(strict_types=1);

namespace App\Tests\Functional\Mcp;

use App\Admin\Infrastructure\Doctrine\Entity\Organization;
use App\Admin\Infrastructure\Doctrine\Entity\User;
use App\Mcp\Infrastructure\Provider\Redmine\RedmineClientInterface;
use App\OAuth\Infrastructure\Security\TokenService;
use App\Tests\Functional\FunctionalTestCase;

final class McpControllerTest extends FunctionalTestCase
{
    private string $accessToken;
    private User $user;
    private ?string $sessionId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization and user
        $org = new Organization('Test Org', 'test-org', 'redmine', $this->now);
        $this->em->persist($org);

        $this->user = new User('test@example.com', 'google_123', $org, $this->now);
        $this->user->approve();
        $this->em->persist($this->user);
        $this->em->flush();

        // Create access token with Redmine credentials
        $tokenService = static::getContainer()->get(TokenService::class);
        $this->accessToken = $tokenService->createAccessToken($this->user, [
            'provider' => 'redmine',
            'org_config' => ['url' => 'https://redmine.example.com'],
            'user_credentials' => ['api_key' => 'test_api_key'],
        ]);

        // Register mock RedmineClient
        $this->registerMockRedmineClient();
    }

    protected function tearDown(): void
    {
        MockRedmineClientFactory::reset();
        parent::tearDown();
    }

    private function createMockRedmineClient(): RedmineClientInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(RedmineClientInterface::class);
    }

    /**
     * Configure base mocks needed for all tests (auth, etc.).
     */
    private function configureBaseMocks(RedmineClientInterface&\PHPUnit\Framework\MockObject\MockObject $mockClient): void
    {
        $mockClient->method('getMyAccount')->willReturn([
            'user' => [
                'id' => 1,
                'login' => 'testuser',
                'firstname' => 'Test',
                'lastname' => 'User',
                'mail' => 'test@example.com',
            ],
        ]);
    }

    private function registerMockRedmineClient(): void
    {
        $mockClient = $this->createMockRedmineClient();

        // Mock getCurrentUser (called by adapter)
        $mockClient->method('getMyAccount')->willReturn([
            'user' => [
                'id' => 1,
                'login' => 'testuser',
                'firstname' => 'Test',
                'lastname' => 'User',
                'mail' => 'test@example.com',
            ],
        ]);

        // Mock getProjects
        $mockClient->method('getMyProjects')->willReturn([
            'projects' => [
                [
                    'id' => 1,
                    'name' => 'Test Project',
                    'identifier' => 'test-project',
                    'description' => 'A test project',
                ],
                [
                    'id' => 2,
                    'name' => 'Another Project',
                    'identifier' => 'another-project',
                    'description' => 'Another test project',
                ],
            ],
        ]);

        // Mock getIssues
        $mockClient->method('getIssues')->willReturn([
            'issues' => [
                [
                    'id' => 123,
                    'subject' => 'Test Issue',
                    'description' => 'Test description',
                    'project' => ['id' => 1, 'name' => 'Test Project'],
                    'status' => ['id' => 1, 'name' => 'New'],
                    'priority' => ['id' => 2, 'name' => 'Normal'],
                    'author' => ['id' => 1, 'name' => 'Test User'],
                    'assigned_to' => ['id' => 1, 'name' => 'Test User'],
                ],
            ],
        ]);

        // Mock getIssue
        $mockClient->method('getIssue')->willReturn([
            'issue' => [
                'id' => 123,
                'subject' => 'Test Issue',
                'description' => 'Test description',
                'project' => ['id' => 1, 'name' => 'Test Project'],
                'status' => ['id' => 1, 'name' => 'New'],
                'priority' => ['id' => 2, 'name' => 'Normal'],
                'author' => ['id' => 1, 'name' => 'Test User'],
                'assigned_to' => ['id' => 1, 'name' => 'Test User'],
                'journals' => [],
                'attachments' => [],
                'allowed_statuses' => [
                    ['id' => 1, 'name' => 'New'],
                    ['id' => 2, 'name' => 'In Progress'],
                    ['id' => 3, 'name' => 'Resolved'],
                ],
            ],
        ]);

        // Mock getIssueStatuses
        $mockClient->method('getIssueStatuses')->willReturn([
            'issue_statuses' => [
                ['id' => 1, 'name' => 'New', 'is_closed' => false],
                ['id' => 2, 'name' => 'In Progress', 'is_closed' => false],
                ['id' => 3, 'name' => 'Resolved', 'is_closed' => true],
            ],
        ]);

        // Mock getProjectActivities
        $mockClient->method('getProjectActivities')->willReturn([
            'project' => [
                'time_entry_activities' => [
                    ['id' => 9, 'name' => 'Development'],
                    ['id' => 10, 'name' => 'Design'],
                ],
            ],
        ]);

        // Mock logTime
        $mockClient->method('logTime')->willReturn([
            'time_entry' => [
                'id' => 999,
                'hours' => 2.0,
                'comments' => 'Test work',
            ],
        ]);

        // Mock getTimeEntries
        $mockClient->method('getTimeEntries')->willReturn([
            'time_entries' => [
                [
                    'id' => 1,
                    'hours' => 2.5,
                    'comments' => 'Test work',
                    'spent_on' => '2024-01-15',
                    'activity' => ['id' => 9, 'name' => 'Development'],
                    'issue' => ['id' => 123],
                    'user' => ['id' => 1, 'name' => 'Test User'],
                ],
            ],
        ]);

        // Mock getAttachment
        $mockClient->method('getAttachment')->willReturn([
            'attachment' => [
                'id' => 1,
                'filename' => 'test.png',
                'filesize' => 1024,
                'content_type' => 'image/png',
                'description' => 'Test attachment',
                'author' => ['name' => 'Test User'],
            ],
        ]);

        // Mock downloadAttachment
        $mockClient->method('downloadAttachment')->willReturn('fake binary content');

        // Mock updateTimeEntry (void)
        $mockClient->method('updateTimeEntry');

        // Mock deleteTimeEntry
        $mockClient->method('deleteTimeEntry')->willReturn('');

        // Mock updateIssue (void)
        $mockClient->method('updateIssue');

        // Mock addIssueNote (void)
        $mockClient->method('addIssueNote');

        // Mock updateJournal (void)
        $mockClient->method('updateJournal');

        // Mock deleteJournal (void)
        $mockClient->method('deleteJournal');

        // Mock getProjectMembers
        $mockClient->method('getProjectMembers')->willReturn([
            'memberships' => [
                [
                    'user' => ['id' => 1, 'name' => 'Test User'],
                    'roles' => [['name' => 'Developer']],
                ],
            ],
        ]);

        // Configure the mock factory (static to persist across kernel reboots)
        MockRedmineClientFactory::setMockClient($mockClient);
    }

    public function testMcpInitializeReturnsServerInfo(): void
    {
        $response = $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('serverInfo', $response['result']);
        $this->assertEquals('mcp-timetracking', $response['result']['serverInfo']['name']);
    }

    public function testMcpToolsListReturnsRedmineTools(): void
    {
        // Initialize first
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        // Then list tools
        $response = $this->mcpRequest('tools/list', []);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response, 'Response: '.json_encode($response));
        $this->assertArrayHasKey('tools', $response['result'], 'Result: '.json_encode($response['result']));

        $toolNames = array_column($response['result']['tools'], 'name');

        // Redmine should have all 12 tools (camelCase names)
        $this->assertContains('listProjects', $toolNames, 'Tools: '.json_encode($toolNames));
        $this->assertContains('listIssues', $toolNames);
        $this->assertContains('getIssueDetails', $toolNames);
        $this->assertContains('listTimeEntries', $toolNames);
        $this->assertContains('getAttachment', $toolNames);
        $this->assertContains('logTime', $toolNames);
        $this->assertContains('updateTimeEntry', $toolNames);
        $this->assertContains('deleteTimeEntry', $toolNames);
        $this->assertContains('updateIssue', $toolNames);
        $this->assertContains('addComment', $toolNames);
        $this->assertContains('updateComment', $toolNames);
        $this->assertContains('deleteComment', $toolNames);
    }

    public function testMcpCallToolListProjects(): void
    {
        // Initialize first
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        // Call listProjects tool
        $response = $this->mcpRequest('tools/call', [
            'name' => 'listProjects',
            'arguments' => [],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('content', $response['result']);
        $this->assertFalse($response['result']['isError'] ?? false);

        // Parse the content (it's JSON in a text content block)
        $content = $response['result']['content'][0]['text'] ?? '';
        $result = json_decode($content, true);

        $this->assertIsArray($result, 'Content: '.$content);
        $this->assertTrue($result['success'] ?? false, 'Result: '.json_encode($result));
        $this->assertCount(2, $result['projects']);
        $this->assertEquals('Test Project', $result['projects'][0]['name']);
    }

    public function testMcpCallToolListIssues(): void
    {
        // Initialize first
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        // Call listIssues tool
        $response = $this->mcpRequest('tools/call', [
            'name' => 'listIssues',
            'arguments' => ['project_id' => 1],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolGetIssueDetails(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'getIssueDetails',
            'arguments' => ['issue_id' => 123],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);

        $content = $response['result']['content'][0]['text'] ?? '';
        $result = json_decode($content, true);

        $this->assertTrue($result['success'] ?? false, 'Result: '.json_encode($result));
        $this->assertEquals(123, $result['issue']['id']);
        $this->assertEquals('Test Issue', $result['issue']['title']);
    }

    public function testMcpCallToolListTimeEntries(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'listTimeEntries',
            'arguments' => [],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolGetAttachment(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'getAttachment',
            'arguments' => ['attachment_id' => 1],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolLogTimeDefaultDate(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('logTime')
            ->with(123, 2.0, 'Test work', 9, date('Y-m-d'))
            ->willReturn(['time_entry' => ['id' => 999]]);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'logTime',
            'arguments' => [
                'issue_id' => 123,
                'hours' => 2.0,
                'comment' => 'Test work',
                'activity_id' => 9,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolLogTimeWithCustomDate(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('logTime')
            ->with(456, 1.5, 'Yesterday work', 10, '2025-12-30')
            ->willReturn(['time_entry' => ['id' => 1000]]);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'logTime',
            'arguments' => [
                'issue_id' => 456,
                'hours' => 1.5,
                'comment' => 'Yesterday work',
                'activity_id' => 10,
                'spent_on' => '2025-12-30',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolUpdateTimeEntryHoursOnly(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('updateTimeEntry')
            ->with(1, 3.0, null, null, null);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateTimeEntry',
            'arguments' => [
                'time_entry_id' => 1,
                'hours' => 3.0,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolUpdateTimeEntryAllParams(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('updateTimeEntry')
            ->with(1, 4.5, 'Updated comment', 10, '2025-12-29');

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateTimeEntry',
            'arguments' => [
                'time_entry_id' => 1,
                'hours' => 4.5,
                'comment' => 'Updated comment',
                'activity_id' => 10,
                'spent_on' => '2025-12-29',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolDeleteTimeEntry(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('deleteTimeEntry')
            ->with(1)
            ->willReturn('');

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'deleteTimeEntry',
            'arguments' => ['time_entry_id' => 1],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolUpdateIssueStatusOnly(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('updateIssue')
            ->with(123, 2, null, null);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateIssue',
            'arguments' => [
                'issue_id' => 123,
                'status_id' => 2,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolUpdateIssueAllParams(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('updateIssue')
            ->with(123, 3, 75, 5);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateIssue',
            'arguments' => [
                'issue_id' => 123,
                'status_id' => 3,
                'assigned_to_id' => 5,
                'done_ratio' => 75,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolAddCommentPublic(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('addIssueNote')
            ->with(123, 'Test comment', false);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'addComment',
            'arguments' => [
                'issue_id' => 123,
                'comment' => 'Test comment',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolAddCommentPrivate(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('addIssueNote')
            ->with(456, 'Private note', true);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'addComment',
            'arguments' => [
                'issue_id' => 456,
                'comment' => 'Private note',
                'private' => true,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolUpdateComment(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('updateJournal')
            ->with(1, 'Updated comment');

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateComment',
            'arguments' => [
                'comment_id' => 1,
                'comment' => 'Updated comment',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpCallToolDeleteComment(): void
    {
        // Create mock with specific expectations
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->expects($this->once())
            ->method('deleteJournal')
            ->with(1);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'deleteComment',
            'arguments' => ['comment_id' => 1],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertFalse($response['result']['isError'] ?? false);
    }

    public function testMcpResourcesListReturnsRedmineResources(): void
    {
        // Initialize first
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        // List resources
        $response = $this->mcpRequest('resources/list', []);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('resources', $response['result']);

        $resourceUris = array_column($response['result']['resources'], 'uri');
        $this->assertContains('provider://statuses', $resourceUris);
    }

    public function testMcpReadResourceStatuses(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('resources/read', [
            'uri' => 'provider://statuses',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('contents', $response['result']);
        $this->assertNotEmpty($response['result']['contents']);

        $content = $response['result']['contents'][0];
        $this->assertEquals('provider://statuses', $content['uri']);
        $this->assertEquals('application/json', $content['mimeType']);

        $data = json_decode($content['text'], true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayHasKey('is_closed', $data[0]);
    }

    public function testMcpReadResourceProjectActivities(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('resources/read', [
            'uri' => 'provider://projects/1/activities',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('contents', $response['result']);
        $this->assertNotEmpty($response['result']['contents']);

        $content = $response['result']['contents'][0];
        $this->assertEquals('provider://projects/1/activities', $content['uri']);
        $this->assertEquals('application/json', $content['mimeType']);

        $data = json_decode($content['text'], true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
    }

    public function testMcpReadResourceProjectMembers(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('resources/read', [
            'uri' => 'provider://projects/1/members',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('contents', $response['result']);
        $this->assertNotEmpty($response['result']['contents']);

        $content = $response['result']['contents'][0];
        $this->assertEquals('provider://projects/1/members', $content['uri']);
        $this->assertEquals('application/json', $content['mimeType']);

        $data = json_decode($content['text'], true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayHasKey('roles', $data[0]);
    }

    // ========================================
    // Unhappy path tests (validation errors)
    // ========================================

    public function testMcpCallToolLogTimeWithZeroHoursReturnsError(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'logTime',
            'arguments' => [
                'issue_id' => 123,
                'hours' => 0,
                'comment' => 'Test work',
                'activity_id' => 9,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('Hours must be greater than 0', $content);
    }

    public function testMcpCallToolLogTimeWithNegativeActivityIdReturnsError(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'logTime',
            'arguments' => [
                'issue_id' => 123,
                'hours' => 2.0,
                'comment' => 'Test work',
                'activity_id' => -1,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('activity_id must be a positive integer', $content);
    }

    public function testMcpCallToolLogTimeWithInvalidActivityIdReturnsError(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        // Activity 999 is not in the allowed list (9=Development, 10=Design)
        $mockClient->method('getProjectActivities')->willReturn([
            'project' => [
                'time_entry_activities' => [
                    ['id' => 9, 'name' => 'Development'],
                    ['id' => 10, 'name' => 'Design'],
                ],
            ],
        ]);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'logTime',
            'arguments' => [
                'issue_id' => 123,
                'hours' => 2.0,
                'comment' => 'Test work',
                'activity_id' => 999,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('Activity ID 999 is not allowed', $content);
    }

    public function testMcpCallToolUpdateIssueWithNoParamsReturnsError(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateIssue',
            'arguments' => [
                'issue_id' => 123,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('At least one field', $content);
    }

    public function testMcpCallToolUpdateIssueWithInvalidDoneRatioReturnsError(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateIssue',
            'arguments' => [
                'issue_id' => 123,
                'done_ratio' => 150,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('done_ratio must be between 0 and 100', $content);
    }

    public function testMcpCallToolUpdateIssueWithInvalidStatusIdReturnsError(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        // Status 999 is not in allowed_statuses
        $mockClient->method('getIssue')->willReturn([
            'issue' => [
                'id' => 123,
                'subject' => 'Test Issue',
                'description' => 'Test description',
                'project' => ['id' => 1, 'name' => 'Test Project'],
                'status' => ['id' => 1, 'name' => 'New'],
                'priority' => ['id' => 2, 'name' => 'Normal'],
                'author' => ['id' => 1, 'name' => 'Test User'],
                'allowed_statuses' => [
                    ['id' => 1, 'name' => 'New'],
                    ['id' => 2, 'name' => 'In Progress'],
                ],
            ],
        ]);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateIssue',
            'arguments' => [
                'issue_id' => 123,
                'status_id' => 999,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('Status ID 999 is not allowed', $content);
    }

    public function testMcpCallToolUpdateTimeEntryWithNoParamsReturnsError(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateTimeEntry',
            'arguments' => [
                'time_entry_id' => 1,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('At least one field', $content);
    }

    public function testMcpCallToolUpdateTimeEntryWithNegativeActivityIdReturnsError(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateTimeEntry',
            'arguments' => [
                'time_entry_id' => 1,
                'activity_id' => -1,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('activity_id must be a positive integer', $content);
    }

    public function testMcpCallToolUpdateIssueWithNegativeStatusIdReturnsError(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateIssue',
            'arguments' => [
                'issue_id' => 123,
                'status_id' => -1,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('status_id must be a positive integer', $content);
    }

    public function testMcpCallToolUpdateIssueWithNegativeAssignedToIdReturnsError(): void
    {
        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'updateIssue',
            'arguments' => [
                'issue_id' => 123,
                'assigned_to_id' => -1,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('assigned_to_id must be a positive integer', $content);
    }

    public function testMcpCallToolGetAttachmentWithImageType(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->method('getAttachment')->willReturn([
            'attachment' => [
                'id' => 1,
                'filename' => 'screenshot.png',
                'filesize' => 2048,
                'content_type' => 'image/png',
                'description' => 'A screenshot',
                'author' => ['name' => 'Test User'],
            ],
        ]);

        $mockClient->method('downloadAttachment')->willReturn(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'getAttachment',
            'arguments' => ['attachment_id' => 1],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertFalse($response['result']['isError'] ?? false);

        // Should have 2 content blocks: text info + image
        $this->assertCount(2, $response['result']['content']);
        $this->assertEquals('text', $response['result']['content'][0]['type']);
        $this->assertEquals('image', $response['result']['content'][1]['type']);
    }

    public function testMcpCallToolGetAttachmentWithNonImageType(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->method('getAttachment')->willReturn([
            'attachment' => [
                'id' => 2,
                'filename' => 'document.pdf',
                'filesize' => 4096,
                'content_type' => 'application/pdf',
                'description' => 'A PDF document',
                'author' => ['name' => 'Test User'],
            ],
        ]);

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'getAttachment',
            'arguments' => ['attachment_id' => 2],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertFalse($response['result']['isError'] ?? false);

        // Should have only 1 content block: text info (no image for PDF)
        $this->assertCount(1, $response['result']['content']);
        $this->assertEquals('text', $response['result']['content'][0]['type']);
        $this->assertStringContainsString('document.pdf', $response['result']['content'][0]['text']);
        $this->assertStringContainsString('cannot be displayed directly', $response['result']['content'][0]['text']);
    }

    public function testMcpCallToolGetAttachmentWithError(): void
    {
        $mockClient = $this->createMockRedmineClient();
        $this->configureBaseMocks($mockClient);

        $mockClient->method('getAttachment')->willThrowException(new \RuntimeException('Attachment not found'));

        MockRedmineClientFactory::setMockClient($mockClient);

        $this->mcpRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]);

        $response = $this->mcpRequest('tools/call', [
            'name' => 'getAttachment',
            'arguments' => ['attachment_id' => 999],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($response['result']['isError'] ?? false);
        $content = $response['result']['content'][0]['text'] ?? '';
        $this->assertStringContainsString('Attachment not found', $content);
    }

    // ========================================
    // Auth tests
    // ========================================

    public function testMcpWithoutAuthReturns401(): void
    {
        $this->client->request(
            'POST',
            '/mcp',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'initialize',
                'params' => [],
                'id' => 1,
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMcpWithInvalidTokenReturns401(): void
    {
        $this->client->request(
            'POST',
            '/mcp',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer invalid_token',
            ],
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'initialize',
                'params' => [],
                'id' => 1,
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function mcpRequest(string $method, array $params): array
    {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->accessToken,
        ];

        // Add session ID for non-initialize requests
        if (null !== $this->sessionId) {
            $headers['HTTP_MCP_SESSION_ID'] = $this->sessionId;
        }

        $this->client->request(
            'POST',
            '/mcp',
            [],
            [],
            $headers,
            json_encode([
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ])
        );

        $response = $this->client->getResponse();

        // Capture session ID from response header
        if ($response->headers->has('Mcp-Session-Id')) {
            $this->sessionId = $response->headers->get('Mcp-Session-Id');
        }

        $content = $response->getContent();

        return json_decode($content, true) ?? [];
    }
}
