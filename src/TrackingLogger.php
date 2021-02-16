<?php

namespace Kyranb\Footprints;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Kyranb\Footprints\Jobs\TrackVisit;

class TrackingLogger implements TrackingLoggerInterface
{
    /**
     * The Request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected Request $request;

    /**
     * Determine whether or not the request should be tracked.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Request
     */
    public function track(Request $request): Request
    {
        $this->request = $request;

        $job = new TrackVisit($this->captureAttributionData());
        if (config('footprints.async') == true) {
            dispatch($job);
        } else {
            $job->handle();
        }

        return $this->request;
    }

    /**
     * @return array
     */
    protected function captureAttributionData()
    {
        return array_merge(
            [
                'footprint'         => $this->request->footprint(),
                'ip'                => $this->captureIp(),
                'landing_domain'    => $this->captureLandingDomain(),
                'landing_page'      => $this->captureLandingPage(),
                'landing_params'    => $this->captureLandingParams(),
                'referral'          => $this->captureReferral(),
                'gclid'             => $this->captureGCLID(),
            ],
            $this->captureUTM(),
            $this->captureReferrer(),
            $this->getCustomParameter()
        );
    }

    /**
     * @return array
     */
    protected function getCustomParameter()
    {
        $arr = [];

        if (config('footprints.custom_parameters')) {
            foreach (config('footprints.custom_parameters') as $parameter) {
                $arr[$parameter] = $this->request->input($parameter);
            }
        }

        return $arr;
    }

    /**
     * @return string|null
     */
    protected function captureIp()
    {
        if (! config('footprints.attribution_ip')) {
            return null;
        }

        return $this->request->ip();
    }

    /**
     * @return string
     */
    protected function captureLandingDomain()
    {
        return $this->request->server('SERVER_NAME');
    }

    /**
     * @return string
     */
    protected function captureLandingPage()
    {
        return $this->request->path();
    }

    /**
     * @return string
     */
    protected function captureLandingParams()
    {
        return $this->request->getQueryString();
    }

    /**
     * @return array
     */
    protected function captureUTM()
    {
        $parameters = ['utm_source', 'utm_campaign', 'utm_medium', 'utm_term', 'utm_content'];

        $utm = [];

        foreach ($parameters as $parameter) {
            if ($this->request->has($parameter)) {
                $utm[$parameter] = $this->request->input($parameter);
            } else {
                $utm[$parameter] = null;
            }
        }

        return $utm;
    }

    /**
     * @return array
     */
    protected function captureReferrer()
    {
        $referrer = [];

        $referrer['referrer_url'] = $this->request->headers->get('referer');

        $parsedUrl = parse_url($referrer['referrer_url']);

        $referrer['referrer_domain'] = isset($parsedUrl['host']) ? $parsedUrl['host'] : null;

        return $referrer;
    }

    /**
     * @return string
     */
    protected function captureGCLID()
    {
        return $this->request->input('gclid');
    }

    /**
     * @return string
     */
    protected function captureReferral()
    {
        return $this->request->input('ref');
    }
}
