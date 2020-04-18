<?php
namespace Deployer;

/*
    A set of scripts to build and deploy resources locally or on GKE

    Script parameters:
    - BRANCH: dev|master
    - TAG
        - local deployment: 'current' (don't use 'latest' as it forces a remote pull in k8s)
        - remote deployment: github tag

    Before executing these scripts, make sure the kubectl context is set accordingly.

    Usage:

    1. Generate service/ cronjob manifests (usually done only once):
    ----
    Local: execute in the local shell:
    php vendor/bin/dep --file=conf/deployer/deploy-k8s.php --hosts=localhost gen-service \
        -o BRANCH=dev

    Remote: execute in the local shell:
    php vendor/bin/dep --file=conf/deployer/deploy-k8s.php --hosts=remote gen-service \
        -o BRANCH=dev

    2. Generate deployment manifests (usually done only once):
    ----
    Local: execute in the local shell:
    php vendor/bin/dep --file=conf/deployer/deploy-k8s.php --hosts=localhost gen-deployment \
        -o BRANCH=dev -o TAG=current

    Remote: execute in the local shell:
    php vendor/bin/dep --file=conf/deployer/deploy-k8s.php --hosts=remote gen-deployment \
        -o BRANCH=dev -o TAG=0.1.2

    3. Deploy new container version (new release, only remotely):
    ----
    Remote: execute in the Cloud shell:
    php vendor/bin/dep --file=conf/deployer/deploy-k8s.php --hosts=remote deploy-remote \
        -o BRANCH=dev -o TAG=0.1.2

*/

// Host configuration
inventory('conf/deployer/hosts.yml');

// Global project environment variables
set('COMPOSE_PROJECT_NAME', 'myproject');

/*
    ------------------------------
    Build and deploy services locally on laptop
    ------------------------------
*/
localhost('localhost')
    ->set('HOST_ENV', 'local')
    ->set('ROOT_DIR', __DIR__.'/../..')
;

/*
    Test
    php vendor/bin/dep --file=conf/deployer/deploy-k8s.php --hosts=localhost test
*/
task('test', function () {
    run("
        DUMMY=\"this is a dummy value\";
        echo \$DUMMY
    ", ['timeout' => null, 'tty' => true]);
});

/*
    Generate service/ cronjob manifests (usually done only once)
*/
task('gen-service', [
    // Set .env file, load into environment variables
    'load-env',
    // Clean up build folder
    'clean-up',
    // Build service manifests
    'service-manifest',
]);

/*
    Set .env file, load into environment variables
*/
task('load-env', function () {
    set('ENV_FILE', parse( "{{ ROOT_DIR }}/conf/env/.env.{{ BRANCH }}.{{ HOST_ENV }}"));
    (new \Symfony\Component\Dotenv\Dotenv())->load(get('ENV_FILE'));
    array_map(function ($var) { set($var, getenv($var)); }, explode(',', $_SERVER['SYMFONY_DOTENV_VARS']));
});

/*
    Clean up build folder
*/
task('clean-up', function () {
    runLocally("
        rm -rf build \
        && mkdir build
    ");
});

/*
    Build Kubernetes service manifests
*/
task('service-manifest', function () {
    // Service
    $conf_file = parse("{{ ROOT_DIR }}/conf/k8s/service.tpl.yml");
    $build_file = parse("{{ ROOT_DIR }}/build/service.yml");
    parse_into($conf_file, $build_file);
    // Cronjob
    $conf_file = parse("{{ ROOT_DIR }}/conf/k8s/cronjob.{{ HOST_ENV }}.tpl.yml");
    $build_file = parse("{{ ROOT_DIR }}/build/cronjob.{{ HOST_ENV }}.yml");
    parse_into($conf_file, $build_file);
});

/*
    Generate deployment manifest
*/
task('gen-deployment', [
    // Set .env file, load into environment variables
    'load-env',
    // Clean up build folder
    'clean-up',
    // Build deployment manifests
    'deployment-manifest',
]);

/*
    Build deployment manifests
*/
task('deployment-manifest', function () {
    $conf_file = parse("{{ ROOT_DIR }}/conf/k8s/deployment.{{ HOST_ENV }}.tpl.yml");
    $build_file = parse("{{ ROOT_DIR }}/build/deployment.yml");
    parse_into($conf_file, $build_file);
});



/*
    Deploy containers on localhost
*/
task('deploy-local', [
    // Set .env file, load into environment variables
    'load-env',
    // Clean up existing build files
    'clean-up',
    // Build docker resources locally (for both local and remote deployment)
    'docker-resources',
    // Build the SF app
    'sf-archive',
    // Build docker containers
    'local:build-containers',
    // Apply Kubernetes deployment
    'local:k8s-deployment',
]);

/*
    Build the SF app archive
    (ignored locally but needed by the Dockerfile)
*/
task('sf-archive', function () {
    cd("{{ ROOT_DIR }}");
    run("
        tar -czf build/sf.tar.gz -C . sf
    ");
});

/*
    Build containers on the Minikube Docker engine
    eval $(..) switches from the localhost docker daemon to the minikube VM one
    sudo is not needed for the VM docker daemon
*/
task('local:build-containers', function () {
    cd("{{ ROOT_DIR }}");
    run("
        cd build \
        && eval $(minikube docker-env) \
        && docker build -f Dockerfile.web \
            -t {{ BRANCH }}-{{ COMPOSE_PROJECT_NAME }}-web:{{ TAG }} \
            --build-arg ROUTER_REQUEST_CONTEXT_HOST={{ ROUTER_REQUEST_CONTEXT_HOST }} \
            --build-arg APACHE_LISTEN_PORT={{ APACHE_LISTEN_PORT }} \
            --build-arg PHP_DISPLAY_ERRORS={{ PHP_DISPLAY_ERRORS }} \
            --build-arg PHP_ERROR_REPORTING=\"{{ PHP_ERROR_REPORTING }}\" \
            .
    ", ['timeout' => null, 'tty' => true]);
});

/*
    Apply Kubernetes deployment
*/
task('local:k8s-deployment', function () {
    cd("{{ ROOT_DIR }}");
    run("
        kubectl apply -f build/deployment.yml
    ", ['timeout' => null, 'tty' => true]);
});


/*
    ------------------------------
    Build and deploy services on Google Cloud Shell
    ------------------------------
*/
host('remote')
    ->set('HOST_ENV', 'remote')
    ->set('ROOT_DIR', __DIR__.'/../..')
    ->set('GCP_PROJECT_ID', function () {
        return run("gcloud config get-value project");
    })
;

/*
    Deploy services on GKE
*/
task('deploy-remote', [
    // Check out tagged version
    'remote:checkout-tag',
    // Set .env file, load into environment variables
    'load-env',
    // Clean up existing build files
    'clean-up',
    // Build docker resources locally
    'docker-resources',
    // Build the SF app
    'sf-archive',
    // Build docker images
    'remote:build-images',
    // Push docker images to repository
    'remote:push-images',
    // Apply Kubernetes deployment
    'remote:k8s-deployment',
    // Git clean up
    'remote:git-clean-up',
]);

/*
    Checkout the tag to release
    -B creates the branch if it doesn’t exist; otherwise, resets the branch
*/
task('remote:checkout-tag', function () {
    cd("{{ ROOT_DIR }}");
    run("
        git fetch --all --tags --prune \
        && git checkout tags/{{ TAG }} -B b{{ TAG }}
    ");
});

/*
    Build images
*/
task('remote:build-images', function () {
    cd("{{ ROOT_DIR }}/build");
    run("
        docker build -f Dockerfile.web \
            -t {{ BRANCH }}-{{ COMPOSE_PROJECT_NAME }}-web:{{ TAG }} \
            --build-arg ROUTER_REQUEST_CONTEXT_HOST={{ ROUTER_REQUEST_CONTEXT_HOST }} \
            --build-arg APACHE_LISTEN_PORT={{ APACHE_LISTEN_PORT }} \
            --build-arg PHP_DISPLAY_ERRORS={{ PHP_DISPLAY_ERRORS }} \
            --build-arg PHP_ERROR_REPORTING=\"{{ PHP_ERROR_REPORTING }}\" \
            .
    ", ['timeout' => null, 'tty' => true]);
});

/*
    Tag local image for the GCR
    Push images to GCR docker repository
*/
task('remote:push-images', function () {
    run("
        docker tag {{ BRANCH }}-{{ COMPOSE_PROJECT_NAME }}-web:{{ TAG }} gcr.io/{{ GCP_PROJECT_ID }}/{{ BRANCH }}-{{ COMPOSE_PROJECT_NAME }}-web:{{ TAG }}
    ", ['timeout' => null, 'tty' => true]);
    run("
        docker push gcr.io/{{ GCP_PROJECT_ID }}/{{ BRANCH }}-{{ COMPOSE_PROJECT_NAME }}-web:{{ TAG }}
    ", ['timeout' => null, 'tty' => true]);
});

/*
    Apply Kubernetes deployment
*/
task('remote:k8s-deployment', function () {
    cd("{{ ROOT_DIR }}");
    run("
        kubectl apply -f build/deployment.yml
    ", ['timeout' => null, 'tty' => true]);
});

/*
    Git clean up - Remove detached branch
*/
task('remote:git-clean-up', function () {
    cd("{{ ROOT_DIR }}");
    run("
        git checkout master \
        && git branch -D b{{ TAG }}
    ");
});


/*
    Common build function
    Build docker-compose resources locally
    Generates a /build folder locally with the resources needed to execute docker-compose
*/
task('docker-resources', [
    'docker-build:env-file',
    'deployment-manifest',
]);

/*
    Build docker .env file locally (on deployment host)
    + copy .env file into the SF root folder
*/
task('docker-build:env-file', function () {
    cd("{{ ROOT_DIR }}");
    runLocally("
        mkdir build/infra \
        && cp -r conf/infra/* build/infra \
        && cp conf/docker/Dockerfile.* build \
        && cp {{ ENV_FILE }} build/.env \
        && cp {{ ENV_FILE }} sf/.env
    ");
});

/*
    Helper function - Parse a source file into a destination file
*/
function parse_into($src_filepath, $dest_filepath) {
    $content = file_get_contents($src_filepath);
    $content = parse($content);
    $fh = fopen($dest_filepath, 'w');
    fwrite($fh, $content);
    fclose($fh);
}
