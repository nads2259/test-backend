<?php

namespace App\DataFixtures;

use App\Entity\UploadedDependencyFile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $upload = new UploadedDependencyFile();
        $upload->setUserId('fixture-user');
        $upload->setOriginalFilename('composer.json');
        $upload->setStatus('in_progress');
        $upload->setCreatedAt(new \DateTimeImmutable());
        $upload->setUpdatedAt(new \DateTimeImmutable());

        $manager->persist($upload);
        $manager->flush();
    }
}
