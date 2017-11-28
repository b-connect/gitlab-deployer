<?php
namespace Deployer;

require 'recipe/common.php';

if (!getenv('DEPLOY_HOST_PATH')) {
  writeln('Please add DEPLOY_HOST_PATH');
  exit(-1);
}

// Project name
set('application', (getenv('DEPLOY_APP_NAME')) ? getenv('DEPLOY_APP_NAME') : 'app');

// Project repository
set('repository', (getenv('DEPLOY_REPOSITORY')) ? getenv('DEPLOY_REPOSITORY') : getenv('CI_REPOSITORY_URL'));

// Set hostname
set('hostname', (getenv('DEPLOY_HOSTNAME')) ? getenv('DEPLOY_HOSTNAME') : getenv('CI_ENVIRONMENT_URL'));

// Set hostname
set('user', (getenv('DEPLOY_USERNAME')) ? getenv('DEPLOY_USERNAME') : 'root');


set('hostpath', getenv('DEPLOY_HOST_PATH'));

// Set alias
set('alias', (getenv('DEPLOY_ALIAS')) ? getenv('DEPLOY_ALIAS') : '');

host('{{hostname}}')
  ->set('deploy_path','/{{hostpath}}/{{CI_ENVIRONMENT_SLUG}}')
  ->user('{{user}}')
  ->addSshOption('UserKnownHostsFile', '/dev/null')
  ->addSshOption('StrictHostKeyChecking', 'no');

// Shared files/dirs between deploys
set('shared_files', [
  'sites/{{drupal_site}}/settings.php',
  'sites/{{drupal_site}}/services.yml',
  '.docksal/docksal-local.env'
]);

set('shared_dirs', [
  'sites/{{drupal_site}}/files',
]);

set('hostnames', function() {
  if (get('alias')) {
    return get('hostname') . ',' . get('alias');
  }
  return get('hostname');
});

set('keep_releases', 1);
set('drupal_site', 'default');

task('deploy2', [
  'deploy:info',
  'deploy:prepare',
  'deploy:lock',
  'deploy:release',
  'deploy:update_code',
  'deploy:shared',
  'deploy:symlink',
  'docksal:setup',
  'docksal:up',
  'deploy:unlock',
  'cleanup'
]);

task('docksal:up', function() {
  cd('{{release_path}}');
  run('fin up');
});

task('docksal:setup', function() {
  if (test('[ ! -f {{release_path}}/.docksal/docksal-local.env ]')) {
    run('touch {{release_path}}/.docksal/docksal-local.env');
    run('echo "VIRTUAL_HOST={{hostnames}}" > {{deploy_path}}/.docksal/docksal-local.env');
  }
});

task('drush:install', function() {
  if (test('[ ! -f {{release_path}}/.docksal/docksal-local.env ]')) {
    run('touch {{release_path}}/.docksal/docksal-local.env');
    run('echo "VIRTUAL_HOST={{hostnames}}\n COMPOSE_PROJECT_NAME={{hostname}}" > {{deploy_path}}/.docksal/docksal-local.env');
  }
});