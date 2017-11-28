<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Test\Downloader;

use Composer\Downloader\FossilDownloader;
use Composer\TestCase;
use Composer\Util\Filesystem;
use Composer\Util\Platform;

class FossilDownloaderTest extends TestCase
{
    /** @var string */
    private $workingDir;

    protected function setUp()
    {
        $this->workingDir = $this->getUniqueTmpDirectory();
    }

    protected function tearDown()
    {
        if (is_dir($this->workingDir)) {
            $fs = new Filesystem;
            $fs->removeDirectory($this->workingDir);
        }
    }

    protected function getDownloaderMock($io = null, $config = null, $executor = null, $filesystem = null)
    {
        $io = $io ?: $this->createMock('Composer\IO\IOInterface');
        $config = $config ?: $this->createMock('Composer\Config');
        $executor = $executor ?: $this->createMock('Composer\Util\ProcessExecutor');
        $filesystem = $filesystem ?: $this->createMock('Composer\Util\Filesystem');

        return new FossilDownloader($io, $config, $executor, $filesystem);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDownloadForPackageWithoutSourceReference()
    {
        $packageMock = $this->createMock('Composer\Package\PackageInterface');
        $packageMock->expects($this->once())
            ->method('getSourceReference')
            ->will($this->returnValue(null));

        $downloader = $this->getDownloaderMock();
        $downloader->download($packageMock, '/path');
    }

    public function testDownload()
    {
        $packageMock = $this->createMock('Composer\Package\PackageInterface');
        $packageMock->expects($this->any())
            ->method('getSourceReference')
            ->will($this->returnValue('trunk'));
        $packageMock->expects($this->once())
            ->method('getSourceUrls')
            ->will($this->returnValue(array('http://fossil.kd2.org/kd2fw/')));
        $processExecutor = $this->createMock('Composer\Util\ProcessExecutor');

        $expectedFossilCommand = $this->getCmd('fossil clone \'http://fossil.kd2.org/kd2fw/\' \'repo.fossil\'');
        $processExecutor->expects($this->at(0))
            ->method('execute')
            ->with($this->equalTo($expectedFossilCommand))
            ->will($this->returnValue(0));

        $expectedFossilCommand = $this->getCmd('fossil open \'repo.fossil\'');
        $processExecutor->expects($this->at(1))
            ->method('execute')
            ->with($this->equalTo($expectedFossilCommand))
            ->will($this->returnValue(0));

        $expectedFossilCommand = $this->getCmd('fossil update \'trunk\'');
        $processExecutor->expects($this->at(2))
            ->method('execute')
            ->with($this->equalTo($expectedFossilCommand))
            ->will($this->returnValue(0));

        $downloader = $this->getDownloaderMock(null, null, $processExecutor);
        $downloader->download($packageMock, 'repo');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUpdateforPackageWithoutSourceReference()
    {
        $initialPackageMock = $this->createMock('Composer\Package\PackageInterface');
        $sourcePackageMock = $this->createMock('Composer\Package\PackageInterface');
        $sourcePackageMock->expects($this->once())
            ->method('getSourceReference')
            ->will($this->returnValue(null));

        $downloader = $this->getDownloaderMock();
        $downloader->update($initialPackageMock, $sourcePackageMock, '/path');
    }

    public function testUpdate()
    {
        // Ensure file exists
        $file = $this->workingDir . '/.fslckout';

        if (!file_exists($file)) {
            touch($file);
        }

        $packageMock = $this->createMock('Composer\Package\PackageInterface');
        $packageMock->expects($this->any())
            ->method('getSourceReference')
            ->will($this->returnValue('trunk'));
        $packageMock->expects($this->any())
            ->method('getSourceUrls')
            ->will($this->returnValue(array('http://fossil.kd2.org/kd2fw/')));
        $processExecutor = $this->createMock('Composer\Util\ProcessExecutor');

        $expectedFossilCommand = $this->getCmd("fossil changes");
        $processExecutor->expects($this->at(0))
            ->method('execute')
            ->with($this->equalTo($expectedFossilCommand))
            ->will($this->returnValue(0));
        $expectedFossilCommand = $this->getCmd("fossil pull && fossil up 'trunk'");
        $processExecutor->expects($this->at(1))
            ->method('execute')
            ->with($this->equalTo($expectedFossilCommand))
            ->will($this->returnValue(0));

        $downloader = $this->getDownloaderMock(null, null, $processExecutor);
        $downloader->update($packageMock, $packageMock, $this->workingDir);
    }

    public function testRemove()
    {
        $expectedResetCommand = $this->getCmd('cd \'composerPath\' && fossil status');

        $packageMock = $this->createMock('Composer\Package\PackageInterface');
        $processExecutor = $this->createMock('Composer\Util\ProcessExecutor');
        $processExecutor->expects($this->any())
            ->method('execute')
            ->with($this->equalTo($expectedResetCommand));
        $filesystem = $this->createMock('Composer\Util\Filesystem');
        $filesystem->expects($this->any())
            ->method('removeDirectory')
            ->with($this->equalTo('composerPath'))
            ->will($this->returnValue(true));

        $downloader = $this->getDownloaderMock(null, null, $processExecutor, $filesystem);
        $downloader->remove($packageMock, 'composerPath');
    }

    public function testGetInstallationSource()
    {
        $downloader = $this->getDownloaderMock(null);

        $this->assertEquals('source', $downloader->getInstallationSource());
    }

    private function getCmd($cmd)
    {
        return Platform::isWindows() ? strtr($cmd, "'", '"') : $cmd;
    }
}
