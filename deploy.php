<?php
namespace Deployer;

require 'recipe/drupal8.php';

$repository = getenv('DEPLOY_REPOSITORY');
$docroot = (getenv('DEPLOY_DOCROOT')) ? getenv('DEPLOY_DOCROOT') : 'docroot';
$host = getenv('DEPLOY_HOST');
$user = getenv('DEPLOY_USER');
$app = getenv('DEPLOY_APPNAME');
$path = getenv('DEPLOY_PATH');

writeln(
  $repository . "\n" .
  $docroot . "\n" .
  $host . "\n" .
  $user . "\n" .
  $app . "\n" .
  $path . "\n"
);

// Project name
set('application', $app);

// Project repository
set('repository', $repository);

set('docroot', $docroot);

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);

// Shared files/dirs between deploys

set('shared_dirs', [
  '{{docroot}}/sites/{{drupal_site}}/files',
]);

set('shared_files', [
  '{{docroot}}/sites/{{drupal_site}}/settings.php',
  '{{docroot}}/sites/{{drupal_site}}/services.yml',
]);

// Hosts
host('dev.b-connect.de')
    ->user($user)
    ->set('deploy_path', $path);

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
