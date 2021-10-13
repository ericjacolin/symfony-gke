<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

// To run console commands from the controller
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

use App\Utils\ValidUtils;

class MailController extends AbstractController
{
    /** @var ValidUtils $vu Validation utilities */
    private $vu;

    public function __construct(
        ValidUtils $vu
    ) {
        $this->vu = $vu;
    }

    /**
     * Send spooled emails (real-time)
     *
     * @Route("/api/email_send_realtime/{api_key}", name="email_send_realtime",
     *    requirements={"api_key": "[\w]{12}"})
     */
    public function emailSendRealtimeAction(KernelInterface $kernel, string $api_key)
    {
        // Validate API key
        $this->vu->validateApiKey($api_key);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
           'command' => 'messenger:consume',
           'receivers' => ['realtime_mailer'],
           '--limit' => 20,
           '--time-limit' => 100,
        ]);
        $output = new NullOutput();
        $application->run($input, $output);
        return new Response();
    }

}
