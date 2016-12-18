<?php

/**
 * Copyright 2016 LINE Corporation
 *
 * LINE Corporation licenses this file to you under the Apache License,
 * version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at:
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

namespace LINE\LINEBot\EchoBot;

use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;
use LINE\LINEBot\Exception\UnknownEventTypeException;
use LINE\LINEBot\Exception\UnknownMessageTypeException;
use Predis;

class Route
{
    public function register(\Slim\App $app)
    {
        $app->post('/callback', function (\Slim\Http\Request $req, \Slim\Http\Response $res) {
            /** @var \LINE\LINEBot $bot */
            $bot = $this->bot;
            /** @var \Monolog\Logger $logger */
            $logger = $this->logger;

            $signature = $req->getHeader(HTTPHeader::LINE_SIGNATURE);
            if (empty($signature)) {
                return $res->withStatus(400, 'Bad Request');
            }

            // Check request with signature and parse request
            try {
                $events = $bot->parseEventRequest($req->getBody(), $signature[0]);
            } catch (InvalidSignatureException $e) {
                return $res->withStatus(400, 'Invalid signature');
            } catch (UnknownEventTypeException $e) {
                return $res->withStatus(400, 'Unknown event type has come');
            } catch (UnknownMessageTypeException $e) {
                return $res->withStatus(400, 'Unknown message type has come');
            } catch (InvalidEventRequestException $e) {
                return $res->withStatus(400, "Invalid event request");
            }

            foreach ($events as $event) {
                if (!($event instanceof MessageEvent)) {
                    $logger->info('Non message event has come');
                    continue;
                }

                if (!($event instanceof TextMessage)) {
                    $logger->info('Non text message has come');
                    continue;
                }

/*
                // docomo chatAPI
                //Redisからcontextを取得
                $from = $event->getReplyToken();
                $redis = new Predis\Client(getenv('REDIS_URL'));
                $context = $redis->get($from);

                $api_key = getenv('DOCOMO_API_KEY') ?: '<your docomo api key>';
                $api_url = sprintf('https://api.apigw.smt.docomo.ne.jp/dialogue/v1/dialogue?APIKEY=%s', $api_key);
                $req_body = array('utt' => $event->getText());
                $req_body['context'] = $context;

                $headers = array(
                    'Content-Type: application/json; charset=UTF-8',
                );
                $options = array(
                    'http'=>array(
                        'method'  => 'POST',
                        'header'  => implode("\r\n", $headers),
                        'content' => json_encode($req_body),
                        )
                    );
                $stream = stream_context_create($options);
                $res = json_decode(file_get_contents($api_url, false, $stream));

                //contextをRedisに保存する
                $redis->set($from, $res->context);

                $replyText = $reply_message = $res->utt;
*/

///*
                // userlocal chatAPI
                $apiKey = getenv('USERLOCAL_API_KEY') ?: '<your userlocal api key>';
                $apiUrl = sprintf('https://chatbot-api.userlocal.jp/api/chat?message=%s&key=%s', $event->getText(), $apiKey);
                $headers = array(
                    'Content-Type: application/json; charset=UTF-8',
                );
                $options = array(
                    'http'=>array(
                        'method'  => 'GET',
                        'header'  => implode("\r\n", $headers)
                        )
                    );
                $stream = stream_context_create($options);
                $res = json_decode(file_get_contents($apiUrl, false, $stream));
                error_log(print_r($res, true));

                $replyText = $reply_message = $res->result;
//*/

                $logger->info('Reply text: ' . $replyText);
                $resp = $bot->replyText($event->getReplyToken(), $replyText);
                $logger->info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
            }

            $res->write('OK');
            return $res;
        });
    }
}
