<?php

namespace App\Domain\Integration\Weather\OpenMeteo;

use App\Infrastructure\Exception\CorruptedData;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\RequestOptions;

final readonly class LiveOpenMeteo implements OpenMeteo
{
    public function __construct(
        private Client $client,
        private Clock $clock,
    ) {
    }

    /**
     * @param array<mixed> $options
     */
    private function request(
        string $path,
        string $method = 'GET',
        array $options = []): string
    {
        $response = $this->client->request($method, $path, $options);

        return $response->getBody()->getContents();
    }

    /**
     * @return array<mixed>
     */
    public function getWeatherStats(
        Coordinate $coordinate,
        SerializableDateTime $date,
    ): array {
        $options = [
            RequestOptions::QUERY => [
                'latitude' => $coordinate->getLatitude()->toFloat(),
                'longitude' => $coordinate->getLongitude()->toFloat(),
                'start_date' => $date->format('Y-m-d'),
                'end_date' => $date->format('Y-m-d'),
                'temperature_unit' => 'celsius',
                'windspeed_unit' => 'kmh',
                'precipitation_unit' => 'mm',
                'timezone' => 'auto',
            ],
        ];

        if (6 <= $this->clock->getCurrentDateTimeImmutable()->diff($date)->format('%a')) {
            // We need to use history API.
            $options['base_uri'] = 'https://archive-api.open-meteo.com/';
            $options[RequestOptions::QUERY]['daily'] = 'weathercode,temperature_2m_max,temperature_2m_min,temperature_2m_mean,apparent_temperature_max,apparent_temperature_min,apparent_temperature_mean,sunrise,sunset,precipitation_sum,rain_sum,snowfall_sum,precipitation_hours,windspeed_10m_max,windgusts_10m_max,winddirection_10m_dominant,shortwave_radiation_sum,et0_fao_evapotranspiration';
            $options[RequestOptions::QUERY]['hourly'] = 'temperature_2m,relativehumidity_2m,dewpoint_2m,apparent_temperature,precipitation,rain,snowfall,weathercode,pressure_msl,cloudcover,cloudcover_low,cloudcover_mid,cloudcover_high,et0_fao_evapotranspiration,vapor_pressure_deficit,windspeed_10m,windspeed_100m,winddirection_10m,winddirection_100m,windgusts_10m';

            try {
                return Json::decode($this->request('v1/archive', 'GET', $options));
            } catch (CorruptedData|ConnectException) {
            }
            throw new OpenMeteoArchiveApiCallHasFailed();
        }

        // We need to use forecast API.
        $options['base_uri'] = 'https://api.open-meteo.com/';
        $options[RequestOptions::QUERY]['daily'] = 'weathercode,temperature_2m_max,temperature_2m_min,apparent_temperature_max,apparent_temperature_min,sunrise,sunset,uv_index_max,uv_index_clear_sky_max,precipitation_sum,rain_sum,showers_sum,snowfall_sum,precipitation_hours,precipitation_probability_max,windspeed_10m_max,windgusts_10m_max,winddirection_10m_dominant,shortwave_radiation_sum,et0_fao_evapotranspiration';
        $options[RequestOptions::QUERY]['hourly'] = 'temperature_2m,relativehumidity_2m,dewpoint_2m,apparent_temperature,precipitation_probability,precipitation,rain,showers,snowfall,snow_depth,weathercode,pressure_msl,surface_pressure,cloudcover,cloudcover_low,cloudcover_mid,cloudcover_high,visibility,evapotranspiration,et0_fao_evapotranspiration,vapor_pressure_deficit,windspeed_10m,windspeed_80m,windspeed_120m,windspeed_180m,winddirection_10m,winddirection_80m,winddirection_120m,winddirection_180m,windgusts_10m,temperature_80m,temperature_120m,temperature_180m';

        try {
            return Json::decode($this->request('v1/forecast', 'GET', $options));
        } catch (CorruptedData|ConnectException) {
        }

        throw new OpenMeteoForecastApiCallHasFailed();
    }
}
