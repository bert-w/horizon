<?php

namespace Laravel\Horizon;

class LuaScripts
{
    /**
     * Update the metrics for a job.
     *
     * KEYS[1] - The name of the key being updated
     * KEYS[2] - The name of the key of the metrics group
     * ARGV[1] - The runtime in milliseconds of the current job
     *
     * @return string
     */
    public static function updateMetrics()
    {
        return <<<'LUA'
            redis.call('hsetnx', KEYS[1], 'throughput', 0)

            redis.call('sadd', KEYS[2], KEYS[1])

            local hash = redis.call('hmget', KEYS[1], 'throughput', 'runtime')

            local throughput = hash[1] + 1
            local runtime = 0

            if hash[2] then
                runtime = ((hash[1] * tonumber(hash[2])) + tonumber(ARGV[1])) / throughput
            else
                runtime = tonumber(ARGV[1])
            end

            redis.call('hmset', KEYS[1], 'throughput', throughput, 'runtime', runtime)
LUA;
    }

    /**
     * Get the Lua script for purging recent and pending jobs off of the queue.
     *
     * KEYS[1] - The name of the recent jobs sorted set
     * KEYS[2] - The name of the pending jobs sorted set
     * ARGV[1] - The prefix of the Horizon keys
     * ARGV[2] - The name of the queue to purge
     *
     * @return string
     */
    public static function purge()
    {
        return <<<'LUA'

            local count = 0
            local cursor = 0

            repeat
                -- Iterate over the recent jobs sorted set
                local scanner = redis.call('zscan', KEYS[1], cursor)
                cursor = scanner[1]

                for i = 1, #scanner[2], 2 do
                    local jobid = scanner[2][i]
                    local hashkey = ARGV[1] .. jobid
                    local job = redis.call('hmget', hashkey, 'status', 'queue')

                    -- Delete the pending/reserved jobs, that match the queue
                    -- name, from the sorted sets as well as the job hash
                    if((job[1] == 'reserved' or job[1] == 'pending') and job[2] == ARGV[2]) then
                        redis.call('zrem', KEYS[1], jobid)
                        redis.call('zrem', KEYS[2], jobid)
                        redis.call('del', hashkey)
                        count = count + 1
                    end
                end
            until cursor == '0'

            return count
LUA;
    }

    /**
     * Get only keys from set A that exist in set B.
     *
     * KEYS[1] - The name of a sorted set A
     * KEYS[2] - The name of a sorted set B
     * ARGV[1] - Start index for pagination
     * ARGV[2] - Stop index for pagination
     *
     * @return string
     */
    public static function sortedSetIntersection()
    {
        return <<<'LUA'
            local lookup = {}
            local result = {}

            for _, v in ipairs(redis.call('zrange', KEYS[2], 0, -1)) do
              lookup[v] = true
            end

            for _, v in ipairs(redis.call('zrange', KEYS[1], tonumber(ARGV[1]), tonumber(ARGV[2]))) do
              if lookup[v] then
                table.insert(result, v)
              end
            end

            return result
LUA;
    }

    /**
     * Get the count for items of set A that exist in set B.
     *
     * KEYS[1] - The name of a sorted set A
     * KEYS[2] - The name of a sorted set B
     * ARGV[1] - Min score of set A
     * ARGV[2] - Max score of set A
     *
     * @return string
     */
    public static function sortedSetIntersectionCount()
    {
        return <<<'LUA'
            local zsetA = redis.call('zrangebyscore', KEYS[1], ARGV[1], ARGV[2])
            local count = 0

            for i = 1, #zsetA do
                if redis.call('zscore', KEYS[2], zsetA[i]) then
                    count = count + 1
                end
            end

            return count
LUA;
    }

    /**
     * Prune all zsets by score.
     *
     * ARGV[1] - Min score
     * ARGV[1] - Max score
     *
     * @return string
     */
    public static function pruneSortedSetsByScore()
    {
        return <<<'LUA'
            local cursor = '0'
            local min = ARGV[1]
            local max = ARGV[2]

            repeat
                local result = redis.call('scan', cursor)
                cursor = result[1]
                local keys = result[2]

                for i, key in ipairs(keys) do
                    if redis.call('type', key).ok == 'zset' then
                        redis.call('zremrangebyscore', key, min, max)
                    end
                end
            until cursor == '0'

            return true
LUA;
    }
}
