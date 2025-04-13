<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RendezvousControllerTest extends WebTestCase
{
    public function testNewRendezvous(): void
    {
        $client = static::createClient();
        
        // 1. Compter le nombre de rendez-vous avant l'ajout
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $initialCount = count($entityManager->getRepository(Rendezvous::class)->findAll());

        // 2. Accéder au formulaire de création
        $crawler = $client->request('GET', '/rendezvous/new');
        $this->assertResponseIsSuccessful();

        // 3. Remplir et soumettre le formulaire
        $form = $crawler->selectButton('Enregistrer')->form([
            'rendezvous[date]' => '2024-01-01',
            'rendezvous[heure]' => '14:00',
            'rendezvous[cinProprietaire]' => '12345678',
            'rendezvous[cinEtudiant]' => '87654321',
            'rendezvous[idLogement]' => '1',
            'rendezvous[status]' => 'pending'
        ]);
        
        $client->submit($form);
        
        // 4. Vérifier la redirection
        $this->assertResponseRedirects('/rendezvous/');
        
        // 5. Vérifier que le rendez-vous a bien été ajouté en base
        $newCount = count($entityManager->getRepository(Rendezvous::class)->findAll());
        $this->assertEquals($initialCount + 1, $newCount);
    }
}