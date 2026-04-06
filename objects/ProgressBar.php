<?php

class ProgressBar
{

    private Async $async;
    private string $job_key;
    private string $progress_bar_key;

    public function __construct(Async $async, string $job_key, string $progress_bar_key)
    {

        $this->async = $async;
        $this->job_key = $job_key;
        $this->progress_bar_key = $progress_bar_key;

    }

    public function refresh(int $current): void
    {

        $this->async->refresh_progress_bar($this->job_key, $this->progress_bar_key, $current);

    }

    public function warning(string $message): void
    {

        $this->async->append_progress_bar_warning($this->job_key, $this->progress_bar_key, $message);

    }

    public function failure(string $message): void
    {

        $this->async->fail_job($this->job_key, $message, $this->progress_bar_key);

    }

    public function download(string $filename, string $filetype): string
    {

        return $this->async->reserve_download($this->job_key, $filename, $filetype, $this->progress_bar_key);

    }

}
