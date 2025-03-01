<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Provider;

use Oro\Bundle\CacheBundle\Provider\MemoryCache;
use Oro\Bundle\ConfigBundle\Config\ConfigDefinitionImmutableBag;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ConfigBundle\Config\GlobalScopeManager;
use Oro\Bundle\EmailBundle\Form\Model\SmtpSettings;
use Oro\Bundle\EmailBundle\Provider\SmtpSettingsProvider;
use Oro\Bundle\SecurityBundle\Encoder\DefaultCrypter;
use Oro\Bundle\SecurityBundle\Encoder\SymmetricCrypterInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SmtpSettingsProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var SmtpSettingsProvider */
    private $provider;

    /** @var ConfigManager */
    private $manager;

    /** @var GlobalScopeManager|\PHPUnit\Framework\MockObject\MockObject */
    private $globalScopeManager;

    /** @var SymmetricCrypterInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $crypter;

    /** @var array */
    private $settings = [
        'oro_email' => [
            'smtp_settings_host' => [
                'value' => 'smtp.orocrm.com',
                'type'  => 'scalar',
            ],
            'smtp_settings_port' => [
                'value' => 465,
                'type'  => 'integer',
            ],
            'smtp_settings_encryption' => [
                'value' => 'ssl',
                'type'  => 'scalar',
            ],
            'smtp_settings_username' => [
                'value' => 'user',
                'type'  => 'scalar',
            ],
            'smtp_settings_password' => [
                'value' => 'pass',
                'type'  => 'scalar',
            ],
        ],
    ];

    protected function setUp(): void
    {
        $this->crypter = new DefaultCrypter();
        $this->settings['oro_email']['smtp_settings_password']['value'] = $this->crypter->encryptData(
            $this->settings['oro_email']['smtp_settings_password']['value']
        );

        $bag = new ConfigDefinitionImmutableBag($this->settings);
        $dispatcher = $this->createMock(EventDispatcher::class);

        $this->manager = new ConfigManager(
            'global',
            $bag,
            $dispatcher,
            new MemoryCache()
        );

        $this->globalScopeManager = $this->createMock(GlobalScopeManager::class);

        $this->manager->addManager('global', $this->globalScopeManager);

        $this->provider = new SmtpSettingsProvider($this->manager, $this->globalScopeManager, $this->crypter);
    }

    public function testSmtpSettingsProvider()
    {
        $smtpSettings = new SmtpSettings();

        $provider = $this->createMock(SmtpSettingsProvider::class);
        $provider->expects($this->once())
            ->method('getSmtpSettings')
            ->with(null)
            ->willReturn($smtpSettings);

        $this->assertInstanceOf(SmtpSettings::class, $smtpSettings);
        $this->assertEquals($smtpSettings, $provider->getSmtpSettings());
    }

    public function testDefaultSmtpSettings()
    {
        $smtpSettings = new SmtpSettings();

        $this->assertNull($smtpSettings->getHost());
        $this->assertNull($smtpSettings->getPort());
        $this->assertNull($smtpSettings->getEncryption());
        $this->assertNull($smtpSettings->getUsername());
        $this->assertNull($smtpSettings->getPassword());
    }

    public function testGetSmtpSettingsAttributes()
    {
        $smtpSettings = $this->provider->getSmtpSettings();

        $this->assertObjectHasAttribute('host', $smtpSettings);
        $this->assertObjectHasAttribute('port', $smtpSettings);
        $this->assertObjectHasAttribute('encryption', $smtpSettings);
        $this->assertObjectHasAttribute('username', $smtpSettings);
        $this->assertObjectHasAttribute('password', $smtpSettings);
    }

    public function testSmtpSettingValues()
    {
        $smtpSettings = $this->provider->getSmtpSettings();

        // check values
        $this->assertSame($smtpSettings->getHost(), $this->getSettingValue('oro_email.smtp_settings_host'));
        $this->assertSame($smtpSettings->getPort(), $this->getSettingValue('oro_email.smtp_settings_port'));
        $this->assertSame($smtpSettings->getEncryption(), $this->getSettingValue('oro_email.smtp_settings_encryption'));
        $this->assertSame($smtpSettings->getUsername(), $this->getSettingValue('oro_email.smtp_settings_username'));
        $this->assertSame(
            $smtpSettings->getPassword(),
            $this->crypter->decryptData($this->getSettingValue('oro_email.smtp_settings_password'))
        );

        // check types
        $this->assertIsString($smtpSettings->getHost());
        $this->assertIsInt($smtpSettings->getPort());
        $this->assertIsString($smtpSettings->getEncryption());
        $this->assertIsString($smtpSettings->getUsername());
        $this->assertIsString($smtpSettings->getPassword());
    }

    private function getSettingValue($key)
    {
        return $this->manager->get($key);
    }
}
