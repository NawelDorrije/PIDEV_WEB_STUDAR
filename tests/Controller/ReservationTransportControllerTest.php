<?php

namespace App\Tests\Controller;

use App\Entity\ReservationTransport;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReservationTransportControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $reservationTransportRepository;
    private string $path = '/reservation/transport/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->reservationTransportRepository = $this->manager->getRepository(ReservationTransport::class);

        foreach ($this->reservationTransportRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('ReservationTransport index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'reservation_transport[adresseDepart]' => 'Testing',
            'reservation_transport[adresseDestination]' => 'Testing',
            'reservation_transport[tempsArrivage]' => 'Testing',
            'reservation_transport[status]' => 'Testing',
            'reservation_transport[cinEtudiant]' => 'Testing',
            'reservation_transport[cinTransporteur]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->reservationTransportRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new ReservationTransport();
        $fixture->setAdresseDepart('My Title');
        $fixture->setAdresseDestination('My Title');
        $fixture->setTempsArrivage('My Title');
        $fixture->setStatus('My Title');
        $fixture->setCinEtudiant('My Title');
        $fixture->setCinTransporteur('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('ReservationTransport');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new ReservationTransport();
        $fixture->setAdresseDepart('Value');
        $fixture->setAdresseDestination('Value');
        $fixture->setTempsArrivage('Value');
        $fixture->setStatus('Value');
        $fixture->setCinEtudiant('Value');
        $fixture->setCinTransporteur('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'reservation_transport[adresseDepart]' => 'Something New',
            'reservation_transport[adresseDestination]' => 'Something New',
            'reservation_transport[tempsArrivage]' => 'Something New',
            'reservation_transport[status]' => 'Something New',
            'reservation_transport[cinEtudiant]' => 'Something New',
            'reservation_transport[cinTransporteur]' => 'Something New',
        ]);

        self::assertResponseRedirects('/reservation/transport/');

        $fixture = $this->reservationTransportRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getAdresseDepart());
        self::assertSame('Something New', $fixture[0]->getAdresseDestination());
        self::assertSame('Something New', $fixture[0]->getTempsArrivage());
        self::assertSame('Something New', $fixture[0]->getStatus());
        self::assertSame('Something New', $fixture[0]->getCinEtudiant());
        self::assertSame('Something New', $fixture[0]->getCinTransporteur());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new ReservationTransport();
        $fixture->setAdresseDepart('Value');
        $fixture->setAdresseDestination('Value');
        $fixture->setTempsArrivage('Value');
        $fixture->setStatus('Value');
        $fixture->setCinEtudiant('Value');
        $fixture->setCinTransporteur('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/reservation/transport/');
        self::assertSame(0, $this->reservationTransportRepository->count([]));
    }
}
