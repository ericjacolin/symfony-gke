<?php

/**
 * A set of scripts to build and deploy resources locally or on GKE
 *
 * Prerequisite: install deployer:
 * $ curl -LO https://deployer.org/deployer.phar && sudo mv deployer.phar /usr/local/bin/dep && sudo chmod +x /usr/local/bin/dep
 *
 * Script parameters:
 * - APP_ENV: dev|prod|oat|etc.
 * - TAG
 *     - local deployment: 'current' (don't use 'latest' as it forces a remote pull in k8s)
 *     - remote deployment: github tag
 *
 * Before executing these scripts, make sure the kubectl context is set accordingly (Minikube or GKE).
 *
 * Usage:
 *
 * 1. Generate service/ cronjob manifests (usually done only once):
 * ----
 * Local: execute in the local shell:
 * $ dep --file=conf/deployer/deploy.php --hosts=localhost gen-service -o APP_ENV=dev
 *
 * Remote: execute in the Cloud shell:
 * $ dep --file=conf/deployer/deploy.php --hosts=remote gen-service -o APP_ENV=oat
 *
 * 2. Deploy image version:
 * ----
 * Local: execute in the local shell: (usually done once, at the beginning)
 * $ dep --file=conf/deployer/deploy.php --hosts=localhost deploy-local -o APP_ENV=dev -o TAG=current
 * Remote: execute in the Cloud shell: (LAST argument is optional, enables reusing build cache on Artifact Registry)
 * $ dep --file=conf/deployer/deploy.php --hosts=remote deploy-remote -o APP_ENV=oat -o TAG=0.2 -o LAST=0.1
 */

namespace Deployer;

use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../../vendor/autoload.php';

// Host configuration
inventory('conf/deployer/hosts.yml');

// Global project environment variables
set('COMPOSE_PROJECT_NAME', 'myproject');
set('GCP_PROJECT_ID', 'myproject-123456');
set('GCP_REGION', 'us-central1');
set('ROOT_DIR', __DIR__.'/../..');
set('CLOUD_SHELL_USER', 'my_user');
set('GITHUB_ACCOUNT', 'my_github_account');
set('GITHUB_REPO', 'myproject');

/**
 *    ------------------------------
 *    Build and deploy services locally on laptop
 *    ------------------------------
 */
localhost('localhost')
    ->set('HOST_ENV', 'local')
    ->set('CACHE_FROM_WEB', function () {
            return '';
    })
    ->set('CACHE_FROM_WEBINIT', function () {
            return '';
    })
;

/**
 * Set .env file, load into environment variables
 */
task(
    'load-env', function () {
        set('ENV_FILE', parse("{{ ROOT_DIR }}/conf/env/.env.{{ APP_ENV }}.{{ HOST_ENV }}"));
        (new Dotenv())->load(get('ENV_FILE'));
        array_map(
            function ($var) {
                set($var, getenv($var));
            },
            explode(',', $_SERVER['SYMFONY_DOTENV_VARS'])
        );
    }
);

/**
 * Generate service/ cronjob manifests (usually done only once)
 */
task(
    'gen-service', [
        // Set .env file, load into environment variables
        'load-env',
        // Build service manifests
        'service-manifest',
    ]
);

/**
 * Build Kubernetes service manifests
 */
task(
    'service-manifest', function () {
        // Create build output folder
        runLocally("rm -rf build && mkdir build");
        // Service manifests
        $conf_file = parse("{{ ROOT_DIR }}/conf/k8s/service.tpl.yml");
        $build_file = parse("{{ ROOT_DIR }}/build/service.yml");
        parse_into($conf_file, $build_file);
        // Cronjob manifests
        $conf_file = parse("{{ ROOT_DIR }}/conf/k8s/cronjob.{{ HOST_ENV }}.tpl.yml");
        $build_file = parse("{{ ROOT_DIR }}/build/cronjob.{{ HOST_ENV }}.yml");
        parse_into($conf_file, $build_file);
    }
);

/**
 * Deploy containers on localhost
 */
task(
    'deploy-local', [
        // Set .env file, load into environment variables
        'load-env',
        // Set local docker context to Minikube
        'local:set-docker-context',
        // Build docker context
        'docker-build-context',
        // Add Symfony local working directory to docker build context
        'local:add-sf-dir',
        // Build docker web image
        'docker-build-web-image',
        // Apply Kubernetes deployment
        'k8s-deployment',
        // Set local docker back to local host
        'local:unset-docker-context',
    ]
);

/**
 * Build docker web image
 */
task(
    'docker-build-web-image', function () {
        cd("{{ ROOT_DIR }}/build");
        run(
            "
                docker build -f Dockerfile.web.{{ HOST_ENV }} \
                    -t {{ COMPOSE_PROJECT_NAME }}-{{ APP_ENV }}-web:{{ TAG }} {{ CACHE_FROM_WEB }} \
                    --build-arg APP_ENV={{ APP_ENV }} \
                    --build-arg ROUTER_REQUEST_CONTEXT_HOST={{ ROUTER_REQUEST_CONTEXT_HOST }} \
                    --build-arg PHP_DISPLAY_ERRORS={{ PHP_DISPLAY_ERRORS }} \
                    --build-arg PHP_ERROR_REPORTING=\"{{ PHP_ERROR_REPORTING }}\" \
                    --build-arg OPCACHE_ENABLE={{ OPCACHE_ENABLE }} \
                    --build-arg OPCACHE_PRELOAD={{ OPCACHE_PRELOAD }} \
                    --build-arg SMTP_ACCOUNT={{ SMTP_ACCOUNT }} \
                    .
            ", ['timeout' => null, 'tty' => true]
        );
    }
);

/**
 * Build docker web init image (remote)
 */
task(
    'remote:docker-build-webinit-image', function () {
        cd("{{ ROOT_DIR }}/build");
        run(
            "
                docker build -f Dockerfile.webinit \
                    -t {{ COMPOSE_PROJECT_NAME }}-{{ APP_ENV }}-webinit:{{ TAG }} {{ CACHE_FROM_WEBINIT }} \
                    --build-arg APP_ENV={{ APP_ENV }} \
                    --build-arg HOST_ENV={{ HOST_ENV }} \
                    --build-arg TAG={{ TAG }} \
                    --build-arg GITHUB_SSH_KEY=\"{{ GITHUB_SSH_KEY }}\" \
                    --build-arg GITHUB_ACCOUNT=\"{{ GITHUB_ACCOUNT }}\" \
                    --build-arg GITHUB_REPO=\"{{ GITHUB_REPO }}\" \
                    .
            ", ['timeout' => null, 'tty' => true]
        );
    }
);

/**
 * Set docker local context to Minikube
 */
task(
    'local:set-docker-context', function () {
        runLocally(
            "
                eval $(minikube docker-env)
            ", ['timeout' => null, 'tty' => true]
        );
    }
);

/**
 * Set docker local context back to local host
 */
task(
    'local:unset-docker-context', function () {
        runLocally(
            "
                eval $(minikube docker-env -u)
            ", ['timeout' => null, 'tty' => true]
        );
    }
);

/**
 * Add Symfony local working directory to docker build context
 */
task(
    'local:add-sf-dir', function () {
        cd("{{ ROOT_DIR }}");
        // Copy ddocker context files
        runLocally(
            "
                cp {{ ENV_FILE }} sf/.env \
                && tar -czf build/sf.tar.gz -C . sf
            "
        );
    }
);

/**
 * Apply Kubernetes deployment
 */
task(
    'k8s-deployment', function () {
        cd("{{ ROOT_DIR }}");
        run(
            "
                kubectl apply -f build/deployment.yml
            ", ['timeout' => null, 'tty' => true]
        );
    }
);


/**
 * ------------------------------
 * Build and deploy services on Google Cloud Shell
 * ------------------------------
 */
host('remote')
    ->set('HOST_ENV', 'remote')
    ->set('GITHUB_SSH_KEY', file_get_contents('/home/'.get('CLOUD_SHELL_USER').'/.ssh/id_rsa'))
    ->set('CACHE_FROM_WEB', function () {
        if (get('LAST')) {
            // --cache-from {{ GCP_REGION }}-docker.pkg.dev/{{ GCP_PROJECT_ID }}/{{ PROJECT }}/{{ COMPOSE_PROJECT_NAME }}-{{ APP_ENV }}-web:{{ TAG }}
            return sprintf(
                '--cache-from %s-docker.pkg.dev/%s/%s-%s-web:%s',
                get('GCP_REGION'),
                get('GCP_PROJECT_ID'),
                get('PROJECT'),
                get('COMPOSE_PROJECT_NAME'),
                get('APP_ENV'),
                get('LAST')
            );
        } else {
            return '';
        }
    })
    ->set('CACHE_FROM_WEBINIT', function () {
        if (get('LAST')) {
            // --cache-from {{ GCP_REGION }}-docker.pkg.dev/{{ GCP_PROJECT_ID }}/{{ PROJECT }}/{{ COMPOSE_PROJECT_NAME }}-{{ APP_ENV }}-webinit:{{ TAG }}
            return sprintf(
                '--cache-from %s-docker.pkg.dev/%s/%s-%s-webinit:%s',
                get('GCP_REGION'),
                get('GCP_PROJECT_ID'),
                get('PROJECT'),
                get('COMPOSE_PROJECT_NAME'),
                get('APP_ENV'),
                get('LAST')
            );
        } else {
            return '';
        }
    })
;

/**
 * Deploy services on GKE
 */
task(
    'deploy-remote', [
        // Set .env file, load into environment variables
        'load-env',
        // Build docker resources locally
        'docker-build-context',
        // Build docker web image
        'docker-build-web-image',
        // Build docker web init image
        'remote:docker-build-webinit-image',
        // Push docker image to repository
        'remote:push-image',
        // Apply Kubernetes deployment
        'k8s-deployment',
    ]
);

/**
 * Tag local image for the Artifact Registry,
 * Push images to Artifact Registry
 */
task(
    'remote:push-image', function () {
        run(
            "
                docker tag {{ COMPOSE_PROJECT_NAME }}-{{ APP_ENV }}-webinit:{{ TAG }} \
                    {{ GCP_REGION }}-docker.pkg.dev/{{ GCP_PROJECT_ID }}/{{ PROJECT }}/{{ COMPOSE_PROJECT_NAME }}-{{ APP_ENV }}-webinit:{{ TAG }}
            ", ['timeout' => null, 'tty' => true]
        );
        run(
            "
                docker push {{ GCP_REGION }}-docker.pkg.dev/{{ GCP_PROJECT_ID }}/{{ PROJECT }}/{{ COMPOSE_PROJECT_NAME }}-{{ APP_ENV }}-webinit:{{ TAG }}
            ", ['timeout' => null, 'tty' => true]
        );
        run(
            "
                docker tag {{ COMPOSE_PROJECT_NAME }}-{{ APP_ENV }}-web:{{ TAG }} \
                    {{ GCP_REGION }}-docker.pkg.dev/{{ GCP_PROJECT_ID }}/{{ PROJECT }}/{{ COMPOSE_PROJECT_NAME }}-{{ APP_ENV }}-web:{{ TAG }}
            ", ['timeout' => null, 'tty' => true]
        );
        run(
            "
                docker push {{ GCP_REGION }}-docker.pkg.dev/{{ GCP_PROJECT_ID }}/{{ PROJECT }}/{{ COMPOSE_PROJECT_NAME }}-{{ APP_ENV }}-web:{{ TAG }}
            ", ['timeout' => null, 'tty' => true]
        );
    }
);

/**
 * Create Docker build context
 */
task(
    'docker-build-context', function () {
        cd("{{ ROOT_DIR }}");
        // Copy ddocker context files
        runLocally(
            "
                rm -rf build \
                && mkdir -p build/infra \
                && cp -r conf/infra/* build/infra \
                && cp -r conf/docker/files build \
                && cp conf/docker/Dockerfile.* build \
                && cp {{ ENV_FILE }} build/.env
            "
        );
        // Copy the deployment manifest
        $conf_file = parse("{{ ROOT_DIR }}/conf/k8s/deployment.{{ HOST_ENV }}.tpl.yml");
        $build_file = parse("{{ ROOT_DIR }}/build/deployment.yml");
        parse_into($conf_file, $build_file);
    }
);

/**
 * Helper function - Parse a source file into a destination file
 *
 * @param string $src_filepath  Source filepath
 * @param string $dest_filepath Destination filepath
 *
 * @return void
 */
function parse_into($src_filepath, $dest_filepath): void
{
    $content = file_get_contents($src_filepath);
    $content = parse($content);
    $fh = fopen($dest_filepath, 'w');
    fwrite($fh, $content);
    fclose($fh);
}
