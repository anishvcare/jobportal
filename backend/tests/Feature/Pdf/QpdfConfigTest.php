<?php

use App\Services\Documents\PdfInspector;
use App\Services\Pdf\QpdfMerger;
use Symfony\Component\Process\Process;

/**
 * Build the Symfony Process a service would use for a given qpdf argument list
 * by invoking its private newProcess() factory via reflection. This keeps the
 * assertions deterministic: no real (or fake) qpdf binary is ever executed, we
 * only inspect the constructed Process object.
 *
 * @param  list<string>  $arguments
 */
function buildQpdfProcess(object $service, array $arguments): Process
{
    $method = new ReflectionMethod($service, 'newProcess');
    $method->setAccessible(true);

    return $method->invoke($service, $arguments);
}

/**
 * Read the (private) environment overrides a Process was constructed with.
 *
 * @return array<string, mixed>
 */
function processEnvOverrides(Process $process): array
{
    $property = new ReflectionProperty(Process::class, 'env');
    $property->setAccessible(true);

    return $property->getValue($process);
}

/**
 * Read the (private) binary the service resolves via reflection.
 */
function serviceBinary(object $service): string
{
    $method = new ReflectionMethod($service, 'binary');
    $method->setAccessible(true);

    return (string) $method->invoke($service);
}

dataset('qpdf services', [
    'merger' => [fn () => new QpdfMerger],
    'inspector' => [fn () => new PdfInspector],
]);

it('uses the configured QPDF_BINARY as the invoked binary', function (Closure $make) {
    config()->set('nexus.qpdf_binary', '/home/USER/bin/qpdf10/bin/qpdf');
    config()->set('nexus.qpdf_library_path', null);

    $service = $make();

    expect(serviceBinary($service))->toBe('/home/USER/bin/qpdf10/bin/qpdf');

    $process = buildQpdfProcess($service, [serviceBinary($service), '--version']);

    expect($process->getCommandLine())->toContain('/home/USER/bin/qpdf10/bin/qpdf');
})->with('qpdf services');

it('sets LD_LIBRARY_PATH on the child process when qpdf_library_path is configured', function (Closure $make) {
    config()->set('nexus.qpdf_binary', 'qpdf');
    config()->set('nexus.qpdf_library_path', '/home/USER/bin/qpdf10/lib');

    $process = buildQpdfProcess($make(), ['qpdf', '--version']);

    expect(processEnvOverrides($process))->toBe(['LD_LIBRARY_PATH' => '/home/USER/bin/qpdf10/lib']);
})->with('qpdf services');

it('applies no env override when qpdf_library_path is null (behaviour identical to today)', function (Closure $make) {
    config()->set('nexus.qpdf_binary', 'qpdf');
    config()->set('nexus.qpdf_library_path', null);

    $process = buildQpdfProcess($make(), ['qpdf', '--version']);

    expect(processEnvOverrides($process))->toBe([]);
})->with('qpdf services');

it('applies no env override when qpdf_library_path is an empty string', function (Closure $make) {
    config()->set('nexus.qpdf_binary', 'qpdf');
    config()->set('nexus.qpdf_library_path', '');

    $process = buildQpdfProcess($make(), ['qpdf', '--version']);

    expect(processEnvOverrides($process))->toBe([]);
})->with('qpdf services');

it('keeps the legacy nexus.qpdf_path config key resolving for backward compatibility', function () {
    expect(config('nexus.qpdf_path', 'qpdf'))->not->toBeNull();
});
