<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Console\Output\OutputInterface;

$console = new Application();

$console
    ->register('database:replace')
    ->setDefinition([
        new InputArgument('search', InputArgument::REQUIRED, 'The value being searched for'),
        new InputArgument('replace', InputArgument::REQUIRED, 'The replacement value that replaces found search values'),
    ])
    ->setDescription('Replace all occurrences of the search string with the replacement string')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $search = $input->getArgument('search');
        $replace = $input->getArgument('replace');

        $progress = new ProgressBar($output, 100);
        $progress->start();

        try {
            $parameters = Yaml::parse(file_get_contents('parameters.yml'));
            $mysql = $parameters['mysql'];
        } catch (ParseException $e) {
            $progress->finish();
            $output->writeln(sprintf('Unable to find the parameters: %s', $e->getMessage()));
            return;
        }

        try {
            $bdd = new PDO(sprintf('mysql:host=%s;dbname=%s', $mysql['host'], $mysql['database']), $mysql['user'], $mysql['password']);
            $allTables = $bdd->query("SHOW TABLES");
            $allTables = $allTables->fetchAll(PDO::FETCH_COLUMN);

            foreach ($allTables as $table) {
                $result = $bdd->query(sprintf('SELECT * FROM %s LIMIT 1', $table));
                $fields = array_keys($result->fetch(PDO::FETCH_ASSOC));

                foreach ($fields as $field) {
                    $query = $bdd->query('UPDATE '.$table.' SET '.$field.' = REPLACE('.$field.', "'.$search.'", "'.$replace.'")');

                    $query->execute();

                    $progress->advance();
                }
            }
        }
        catch (Exception $e) {
            $progress->finish();
            $output->writeln('Erreur : ' . $e->getMessage());
            return;
        }

        $progress->finish();
    })
;

$console->run();
