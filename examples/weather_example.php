<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Mhrlife\PhpaiKit\Attributes\Tool;

// Define input parameter class
class WeatherParams
{
    public string $location;
    public ?string $unit = 'celsius';
}

// Define output model class
class WeatherReport
{
    public string $location;
    public float $temperature;
    public string $description;
}

// Define tool function with #[Tool] attribute
#[Tool("get_weather", "Get current weather for a location")]
function getWeather(WeatherParams $params): array
{
    // Simulate weather API call
    $weatherData = [
        'Paris' => ['temp' => 22.5, 'desc' => 'Sunny with clear skies'],
        'London' => ['temp' => 18.0, 'desc' => 'Cloudy with light rain'],
        'New York' => ['temp' => 25.0, 'desc' => 'Partly cloudy'],
    ];

    $data = $weatherData[$params->location] ?? ['temp' => 20.0, 'desc' => 'Unknown'];

    return [
        'location' => $params->location,
        'temperature' => $data['temp'],
        'description' => $data['desc'],
    ];
}


// Create OpenAI client
$openai = \OpenAI::factory()
    ->withApiKey(getenv("LLM_COURSE_OPENROUTER_API_KEY"))
    ->withBaseUri("https://openrouter.ai/api/v1")
    ->make();

// Create agent with tool and output model
$agent = \Mhrlife\PhpaiKit\create_agent(
    $openai,
    tools: [getWeather(...)],
    output: WeatherReport::class
);

echo "Running agent...\n\n";

$report = $agent->run("What's the weather like in Paris?");

echo "Location: {$report->location}\n";
echo "Temperature: {$report->temperature}°C\n";
echo "Description: {$report->description}\n";
