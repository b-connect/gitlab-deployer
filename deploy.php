<?php
namespace Deployer;

require 'recipe/drupal8.php';

$repository = getenv('REPOSITORY');
$docroot = (getenv('DOCROOT')) ? getenv('DOCROOT') : 'docroot';
$host = getenv('HOST');
$user = getenv('USER');
$app = getenv('APPNAME');
$path = getenv('PATH');

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
