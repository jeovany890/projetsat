<?php

namespace App\Command;

use App\Entity\Administrateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Créer un compte administrateur',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Vérifier si un admin existe déjà
        $existingAdmin = $this->entityManager->getRepository(Administrateur::class)->findOneBy([]);
        
        if ($existingAdmin) {
            $io->warning('Un administrateur existe déjà : ' . $existingAdmin->getEmail());
            
            if (!$io->confirm('Voulez-vous créer un autre administrateur ?', false)) {
                return Command::SUCCESS;
            }
        }

        // Demander les informations
        $prenom = $io->ask('Prénom', 'Admin');
        $nom = $io->ask('Nom', 'SAT');
        $email = $io->ask('Email', 'admin@satplatform.bj');
        $password = $io->askHidden('Mot de passe (min 8 caractères)');

        if (strlen($password) < 8) {
            $io->error('Le mot de passe doit contenir au moins 8 caractères.');
            return Command::FAILURE;
        }

        // Créer l'admin
        $admin = new Administrateur();
        $admin->setPrenom($prenom);
        $admin->setNom($nom);
        $admin->setEmail($email);
        $admin->setEstActif(true);
        $admin->setEstVerifie(true);
        $admin->setEstPremiereConnexion(false);
        
        $hashedPassword = $this->passwordHasher->hashPassword($admin, $password);
        $admin->setMotDePasse($hashedPassword);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success('✅ Administrateur créé avec succès !');
        $io->table(
            ['Champ', 'Valeur'],
            [
                ['Prénom', $prenom],
                ['Nom', $nom],
                ['Email', $email],
                ['Rôle', 'ROLE_ADMIN'],
            ]
        );

        $io->note('Vous pouvez maintenant vous connecter sur : http://localhost:8000/login');

        return Command::SUCCESS;
    }
}