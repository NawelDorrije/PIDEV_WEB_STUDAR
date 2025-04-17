<?php

namespace App\Tests\Controller;

use App\Entity\ReservationLogement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReservationLogementControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $reservationLogementRepository;
    private string $path = '/reservation/logement/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->reservationLogementRepository = $this->manager->getRepository(ReservationLogement::class);

        foreach ($this->reservationLogementRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('ReservationLogement index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'reservation_logement[dateDebut]' => 'Testing',
            'reservation_logement[dateFin]' => 'Testing',
            'reservation_logement[status]' => 'Testing',
            'reservation_logement[cinEtudiant]' => 'Testing',
            'reservation_logement[cinProprietaire]' => 'Testing',
            'reservation_logement[idLogement]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->reservationLogementRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new ReservationLogement();
        $fixture->setDateDebut('My Title');
        $fixture->setDateFin('My Title');
        $fixture->setStatus('My Title');
        $fixture->setCinEtudiant('My Title');
        $fixture->setCinProprietaire('My Title');
        $fixture->setIdLogement('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('ReservationLogement');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new ReservationLogement();
        $fixture->setDateDebut('Value');
        $fixture->setDateFin('Value');
        $fixture->setStatus('Value');
        $fixture->setCinEtudiant('Value');
        $fixture->setCinProprietaire('Value');
        $fixture->setIdLogement('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'reservation_logement[dateDebut]' => 'Something New',
            'reservation_logement[dateFin]' => 'Something New',
            'reservation_logement[status]' => 'Something New',
            'reservation_logement[cinEtudiant]' => 'Something New',
            'reservation_logement[cinProprietaire]' => 'Something New',
            'reservation_logement[idLogement]' => 'Something New',
        ]);

        self::assertResponseRedirects('/reservation/logement/');

        $fixture = $this->reservationLogementRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getDateDebut());
        self::assertSame('Something New', $fixture[0]->getDateFin());
        self::assertSame('Something New', $fixture[0]->getStatus());
        self::assertSame('Something New', $fixture[0]->getCinEtudiant());
        self::assertSame('Something New', $fixture[0]->getCinProprietaire());
        self::assertSame('Something New', $fixture[0]->getIdLogement());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new ReservationLogement();
        $fixture->setDateDebut('Value');
        $fixture->setDateFin('Value');
        $fixture->setStatus('Value');
        $fixture->setCinEtudiant('Value');
        $fixture->setCinProprietaire('Value');
        $fixture->setIdLogement('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/reservation/logement/');
        self::assertSame(0, $this->reservationLogementRepository->count([]));
    }
}
