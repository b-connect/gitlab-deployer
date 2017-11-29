<?php
namespace Deployer;

use function Deployer\Support\str_contains;

require 'recipe/common.php';

if (!getenv('DEPLOY_HOST_PATH')) {
  writeln('Please add DEPLOY_HOST_PATH');
  exit(-1);
}

// Project name
set('application', (getenv('DEPLOY_APP_NAME')) ? getenv('DEPLOY_APP_NAME') : 'app');
// Set environment slug name
set('env_alias', getenv('CI_ENVIRONMENT_SLUG'));
// Env url for docksal and folder
set('env_url', getenv('CI_ENVIRONMENT_URL'));
// Set default drupal installation profile
set('drupal_profile', (getenv('DEPLOY_DRUPAL_PROFILE')) ? getenv('DEPLOY_DRUPAL_PROFILE') : 'standard' );
// Project repository
set('repository', (getenv('DEPLOY_REPOSITORY')) ? getenv('DEPLOY_REPOSITORY') : getenv('CI_REPOSITORY_URL'));
// Set hostname
set('hostname', getenv('DEPLOY_HOSTNAME'));


set('npm_paths', []);
if (getenv('DEPLOY_NPM_PATHS') !== '') {
  set('npm_paths', explode(',', getenv('DEPLOY_NPM_PATHS')));
}

// Get a usefull hostname for docksal
$hostname_docksal =  getenv('CI_ENVIRONMENT_URL');
$hostname_docksal = parse_url($hostname_docksal,  PHP_URL_HOST);
set('hostname_docksal', $hostname_docksal);

// We do not need tty
set('git_tty', false);

// Set user for ssh connection
set('user', (getenv('DEPLOY_USERNAME')) ? getenv('DEPLOY_USERNAME') : 'root');

// Set deploy host path
set('hostpath', getenv('DEPLOY_HOST_PATH'));

// Set alias
set('alias', (getenv('DEPLOY_ALIAS')) ? getenv('DEPLOY_ALIAS') : '');

// Set deploy path base on url and
set('deploy_path', function() {
  return get('hostpath') . '/' . get('env_alias') . '/' . get('hostname_docksal');
});

set('hostnames', function() {
  if (get('alias')) {
    return get('hostname_docksal') . ',' . get('alias');
  }
  return get('hostname_docksal');
});

set('current_path', function() {
  return get('deploy_path') . '/release';
});

task('deploy:prepare', function() {
  $result = run('echo $0');
  if (!str_contains($result, 'bash') && !str_contains($result, 'sh')) {
      throw new \RuntimeException(
          'Shell on your server is not POSIX-compliant. Please change to sh, bash or similar.'
      );
  }
  run('if [ ! -d {{deploy_path}} ]; then mkdir -p {{deploy_path}}; fi');
  // Create metadata .dep dir.
  run("cd {{deploy_path}} && if [ ! -d .dep ]; then mkdir .dep; fi");
});

set('release_path', function () {
  return get('deploy_path') . '/release';
});

task('deploy:update_code', function() {
  $repository = trim(get('repository'));
  $branch = get('branch');
  $git = get('bin/git');

  $recursive = get('git_recursive', true) ? '--recursive' : '';
  $options = [
      'tty' => get('git_tty', false),
  ];
  $at = '';
  if (!empty($branch)) {
      $at = "-b $branch";
  }
  // If option `tag` is set
  if (input()->hasOption('tag')) {
      $tag = input()->getOption('tag');
      if (!empty($tag)) {
          $at = "-b $tag";
      }
  }
  // If option `tag` is not set and option `revision` is set
  if (empty($tag) && input()->hasOption('revision')) {
      $revision = input()->getOption('revision');
      if (!empty($revision)) {
          $depth = '';
      }
  }
  if (test('[ ! -d {{deploy_path}}/release ]')) {
    try {
      run("$git clone $at $recursive -q --reference {{deploy_path}}/release --dissociate $repository  {{deploy_path}}/release 2>&1", $options);
    } catch (\Throwable $exception) {
        run("$git clone $at $recursive -q $repository {{deploy_path}}/release 2>&1", $options);
    }
  } else {

      // if we're using git cache this would be identical to above code in catch - full clone. If not, it would create shallow clone.
    cd("{{deploy_path}}/release");
    run('git fetch --all');
    if (!empty($branch)) {
      run('git checkout {{branch}}');
    } elseif (!empty($revision)) {
      run("$git checkout $revision");
    } elseif(!empty($branch)) {
      run("$git checkout $branch");
    } else {
      run('git checkout master && git pull origin master');
    }
  }
});

set('vendor_bin_path', function() {
    if (test('[ -d {{release_path}}/vendor/bin ]')) {
      return get('release_path') . '/vendor/bin';
    }
    if (test('[ -d {{release_path}}/bin ]')) {
      return get('release_path') . '/bin';
    }
});

set('drush_installed', function() {
  if ( test(['-f {{vendor_bin_path}}/drush']) ) {
    return true;
  }
  return false;
});

task('deploy:vendors', function() {
  cd('{{deploy_path}}/release');
  run('composer install');
});


task('docksal:up', function() {
  cd('{{deploy_path}}/release');
  run('fin up');
  writeln('Sleep 5 seconds for db.');
  sleep(5);
  writeln('Ready.');
});

task('docksal:setup', function() {
  run('echo "VIRTUAL_HOST={{hostnames}}" > {{deploy_path}}/release/.docksal/docksal-local.env');
  run('echo "COMPOSE_PROJECT_NAME={{env_url}}" >> {{deploy_path}}/release/.docksal/docksal-local.env');
});

task('drupal:check', function() {
  $test = false;
  if ( test('[ -f {{release_path}}/docroot/sites/default/settings.php ]') ) {
    $test = true;
  } else {
    writeln('Settings.php do not exists');
  }
  set('drupal_installed', $test);
});

task('drush:install', function() {
  if (get('drupal_installed') === TRUE) {
    writeln('Drupal already installed');
    return;
  }
  writeln('Install drupal.');
  $options = '';
  if (getenv('DEPLOY_DRUPAL_CONFIG_DIR') !== '') {
    $options = ' --config-dir=' . getenv('DEPLOY_DRUPAL_CONFIG_DIR');
  }
  if (getenv('DEPLOY_DRUPAL_ACCOUNT') !== '') {
    $options .= ' --account-name=' . getenv('DEPLOY_DRUPAL_ACCOUNT');
  }
  if (getenv('DEPLOY_DRUPAL_MAIL') !== '') {
    $options .= ' --account-mail=' . getenv('DEPLOY_DRUPAL_MAIL');
  }
  if (getenv('DEPLOY_DRUPAL_PASS') !== '') {
    $options .= ' --account-pass=' . getenv('DEPLOY_DRUPAL_PASS');
  }
  cd('{{release_path}}/docroot');
  run('fin drush -l {{hostname_docksal}} si {{drupal_profile}} --db-url=mysql://root:root@db/drupal '.$options.' -y');
  if (getenv('DEPLOY_DRUPAL_UUID')) {
    // run('fin drush cset system.site uuid ' . getenv('DEPLOY_DRUPAL_UUID'));
  }
});

task('drush:updatedb', function() {
  if (!get('drupal_installed')) {
    return;
  }
  cd('{{release_path}}/docroot');
  run('fin drush -l {{hostname_docksal}} updatedb -y');
});

task('drush:ci', function() {
  if (!get('drupal_installed')) {
    return;
  }
  cd('{{release_path}}/docroot');
  run('fin drush -l {{hostname_docksal}} cim -y');
});

task('drush:cr', function() {
  if (!get('drupal_installed')) {
    return;
  }
  cd('{{release_path}}/docroot');
  run('fin drush -l {{hostname_docksal}} cr -y');
});

task('npm:install', function() {
  $paths = get('npm_paths');
  foreach ($paths as $path) {
    writeln('npm process ' . $path);
    cd("{{release_path}}/" . $path);
    run('docker run -i --rm --name node-runner -v "$PWD":/usr/src/app -w /usr/src/app node:8 npm i');
    run('docker run -i --rm --name node-runner -v "$PWD":/usr/src/app -w /usr/src/app node:8 npm run build:prod');
  }
});

task('deploy', [
  'deploy:info',
  'deploy:prepare',
  'deploy:lock',
  'deploy:update_code',
  'deploy:vendors',
  'docksal:setup',
  'docksal:up',
  'drupal:check',
  'drush:install',
  'drush:updatedb',
  'npm:install',
  'drush:cr',
  'drush:ci',
  'deploy:unlock'
]);

host(get('hostname'))
  ->user(getenv('DEPLOY_USERNAME'))
  ->set('timeout', 1200)
  ->set('env', ['NVM_DIR' => '$HOME/.nvm'])
  ->addSshOption('UserKnownHostsFile', '/dev/null')
  ->addSshOption('StrictHostKeyChecking', 'no');

after('deploy:failed', 'deploy:unlock');