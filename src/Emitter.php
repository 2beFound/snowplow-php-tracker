<?php

/*
    Emitter.php

    Copyright (c) 2014 Snowplow Analytics Ltd. All rights reserved.

    This program is licensed to you under the Apache License Version 2.0,
    and you may not use this file except in compliance with the Apache License
    Version 2.0. You may obtain a copy of the Apache License Version 2.0 at
    http://www.apache.org/licenses/LICENSE-2.0.

    Unless required by applicable law or agreed to in writing,
    software distributed under the Apache License Version 2.0 is distributed on
    an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
    express or implied. See the Apache License Version 2.0 for the specific
    language governing permissions and limitations there under.

    Authors: Joshua Beemster
    Copyright: Copyright (c) 2014 Snowplow Analytics Ltd
    License: Apache License Version 2.0
*/
namespace Snowplow\Tracker;

class Emitter {
    // Emitter Constants
    const POST_REQ_SCEHMA = "iglu:com.snowplowanalytics.snowplow/payload_data/jsonschema/1-0-1";
    const POST_PATH = "/com.snowplowanalytics.snowplow/tp2";

    // Emitter Parameters
    private $buffer_size;
    private $buffer = array();

    // Debug Parameters
    private $debug_mode = false;
    private $debug_file;
    private $path;

    /**
     * Setup emitter parameters
     * - Stores the emitter sub-class object
     * - Sets the emitter buffer size
     * - Sets if we are going to debug
     *
     * @param string $type
     * @param bool $debug
     * @param int $buffer_size
     */
    public function setup($type, $debug, $buffer_size) {
        $this->buffer_size = $buffer_size;
        $this->debug_mode = $debug;
        if ($this->debug_mode) {
            $this->initDebug($type);
        }
    }

    /**
     * Sends the buffer to the configured emitter for sending
     *
     * @param bool $force
     * @param string $nuid
     */
    public function flush($force = false, $nuid) {
        if (count($this->buffer) > 0 || $force) {
            $res = $this->send($this->buffer, $nuid, $force);
            if (is_bool($res) && $res) {
                if ($this->debug_mode) {
                    fwrite($this->debug_file,"Emitter sent payload successfully\n");
                }
                $this->buffer = array();
            }
            else {
                if ($this->debug_mode) {
                    fwrite($this->debug_file,$res."\nPayload: ".json_encode($this->buffer)."\n\n");
                }
                $this->buffer = array();
            }
        }
    }

    /**
     * Pushes the event payload into the emitter buffer
     * When buffer is full it flushes the buffer
     *
     * @param array $final_payload - Takes the Trackers Payload as a parameter
     * @param string $nuid - The trackers network user id
     */
    public function addEvent($final_payload, $nuid) {
        array_push($this->buffer, $final_payload);
        if (count($this->buffer) >= $this->buffer_size) {
            $this->flush(false, $nuid);
        }
    }

    /**
     * Turns of debug_mode for the emitter
     * - Closes the log resource
     * - Sends a command to stop logging local information in sub emitter classes.
     *
     * @param bool $deleteLocal - Delete all local information
     */
    public function debugSwitch($deleteLocal) {
        $this->debug_mode = false;
        fclose($this->debug_file);
        if ($deleteLocal) {
            unlink($this->path);
        }
    }

    // Return Functions
    /**
     * Returns the buffer_size
     *
     * @return int
     */
    public function returnBufferSize() {
        return $this->buffer_size;
    }

    /**
     * Returns the events buffer
     *
     * @return array
     */
    public function returnBuffer() {
        return $this->buffer;
    }

    // Make Functions
    /**
     * Returns a boolean of if debug mode is on or not
     *
     * @return bool
     */
    public function returnDebugMode() {
        return $this->debug_mode;
    }

    /**
     * Returns the emitter debug file
     *
     * @return resource|null
     */
    public function returnDebugFile() {
        return $this->debug_file;
    }

    // Debug Setup
    /**
     * Initialize Debug Logging Paths and Files
     *
     * @param string $emitter_type - Type of emitter we are logging for
     */
    private function initDebug($emitter_type) {
        $this->debug_mode = true;
        $id = uniqid("", true);
        $this->initDebugLogFiles($id, $emitter_type);
        fwrite($this->debug_file,"Event Log File\n");
        fwrite($this->debug_file,"Emitter: ".$emitter_type."\n");
        fwrite($this->debug_file,"ID: ".$id."\n\n");
    }

    /**
     * Creates the debug log files
     *
     * @param string $id - Random id for the log file
     * @param string $type - Type of emitter we are logging for
     */
    private function initDebugLogFiles($id, $type) {
        $root_path = dirname(__DIR__);
        if (!is_dir($root_path."/debug")) {
            mkdir($root_path."/debug");
        }
        $this->path = $root_path."/debug/".$type."-events-log-".$id.".log";
        $this->debug_file = fopen($this->path,"w");
    }
}
