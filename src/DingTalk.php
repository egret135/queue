<?php

namespace Egret\Queue;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;

/**
 * 钉钉机器人通知
 * Class DingTalk
 * @package Egret\Queue
 */
class DingTalk
{
    protected $url = 'https://oapi.dingtalk.com';

    protected $token = '';

    protected $isSign = true;

    protected $sign = '';

    protected $client;
    /**
     * @var Logger
     */
    protected $logger;

    public function __construct($token, $isSign = true, $sign = '')
    {
        $this->token = $token;
        $this->isSign = $isSign;
        $this->sign = $sign;
        $this->client = new Client([
            'base_uri' => $this->url,
            'timeout' => 2.0,
        ]);
    }

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 发送文本消息
     * @param string $msg 发送的消息
     * @param array $atMobiles 被@的人的手机号
     * @param bool $isAtAll 是否@所有人
     */
    public function sendText($msg, array $atMobiles = [], $isAtAll = false)
    {
        $data = [
            'msgtype' => 'text',
            'text' => [
                'content' => $msg,
            ],
            'at' => [
                'atMobiles' => $atMobiles,
                'isAtAll' => $isAtAll
            ]
        ];

        $this->send($data);
    }

    /**
     * @param string $text 消息内容。如果太长只会部分展示
     * @param string $title 消息标题
     * @param string $messageUrl 点击消息跳转的URL
     * @param string $picUrl 图片URL
     */
    public function sendLink($text, $title, $messageUrl, $picUrl = '')
    {
        $data = [
            'msgtype' => 'link',
            'link' => [
                'text' => $text,
                'title' => $title,
                'picUrl' => $picUrl,
                'messageUrl' => $messageUrl
            ]
        ];

        $this->send($data);
    }

    public function sendMarkdown($title, $text, $atMobiles = [], $isAtAll = false)
    {
        $data = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $title,
                'text' => $text
            ],
            'at' => [
                'atMobiles' => $atMobiles,
                'isAtAll' => $isAtAll
            ]
        ];

        $this->send($data);
    }

    private function sign()
    {
        $ts = $this->msectime();
        $str = $ts . "\n" . $this->sign;
        $str = hash_hmac('sha256', $str, $this->sign, true);
        $str = base64_encode($str);
        $str = urlencode($str);
        return [$ts, $str];
    }

    private function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }

    private function send($data)
    {
        try {
            list($timestamp, $sign) = $this->sign();
            $response = $this->client->request('POST', "/robot/send?access_token={$this->token}&timestamp={$timestamp}&sign={$sign}", [
                'json' => $data
            ]);
        } catch (RequestException $exception) {
            // 记录错误日志
            $response = $exception->getResponse();
            if ($this->logger) {
                $this->logger->error('钉钉发送消息失败', json_decode((string)$response->getBody(), true));
            }
        }
    }
}