<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class RemoteIntegrationTest extends TestCase
{
    private array $originalConfig;

    protected function setUp(): void
    {
        global $config;

        $this->originalConfig = $config;
        require_once 'inc/remote.php';
    }

    protected function tearDown(): void
    {
        global $config;

        $config = $this->originalConfig;
    }

    public function testPasswordAuthenticatedSftpAndScpWriteRealRemoteFiles(): void
    {
        $base = [
            'host' => 'sftp',
            'port' => 2222,
            'auth' => [
                'method' => 'plain',
                'username' => 'e2e',
                'password' => 'password',
            ],
        ];

        $connection = ssh2_connect('sftp', 2222);
        self::assertNotFalse($connection);
        self::assertTrue(ssh2_auth_password($connection, 'e2e', 'password'));
        $sftp = ssh2_sftp($connection);
        self::assertNotFalse($sftp);
        if (!is_dir('ssh2.sftp://' . (int) $sftp . '/config/upload')) {
            self::assertTrue(mkdir('ssh2.sftp://' . (int) $sftp . '/config/upload'));
        }

        $sftpRemote = new \Remote($base + ['type' => 'sftp']);
        $sftpRemote->write('SFTP integration payload', '/config/upload/sftp.txt');
        $sftp = ssh2_sftp($sftpRemote->connection);
        self::assertNotFalse($sftp);
        self::assertSame(
            'SFTP integration payload',
            file_get_contents('ssh2.sftp://' . (int) $sftp . '/config/upload/sftp.txt'),
        );

        $scpRemote = new \Remote($base + ['type' => 'scp']);
        $scpRemote->write('SCP integration payload', '/config/upload/scp.txt');
        $scpSftp = ssh2_sftp($scpRemote->connection);
        self::assertNotFalse($scpSftp);
        self::assertSame(
            'SCP integration payload',
            file_get_contents('ssh2.sftp://' . (int) $scpSftp . '/config/upload/scp.txt'),
        );
    }
}
