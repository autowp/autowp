CREATE TABLE traffic_source (
 id text PRIMARY KEY,
 name text NOT NULL,
 networks text[] NOT NULL DEFAULT '{}',
 dns_suffixes text[] NOT NULL DEFAULT '{}',
 reference_urls text[] NOT NULL DEFAULT '{}'
);

INSERT INTO traffic_source (id,name,networks,dns_suffixes,reference_urls) VALUES
('googlebot','Googlebot',ARRAY[]::text[],ARRAY['googlebot.com']::text[],ARRAY[]::text[]),
('google','Google',ARRAY[]::text[],ARRAY['google.com']::text[],ARRAY[]::text[]),
('bingbot','Bingbot',ARRAY[]::text[],ARRAY['search.msn.com']::text[],ARRAY[]::text[]),
('yandex','Yandex',ARRAY[]::text[],ARRAY['yandex.ru','yandex.net','yandex.com']::text[],ARRAY[]::text[]),
('openai','OpenAI',ARRAY['104.210.140.128/28','13.66.216.176/28','132.196.86.0/24','135.234.64.0/24','172.182.193.224/28','172.182.193.80/28','172.182.194.144/28','172.182.194.32/28','172.182.195.48/28','172.182.202.0/25','172.182.207.0/25','172.182.209.208/28','172.182.211.192/28','172.182.213.192/28','172.182.224.0/28','172.203.190.112/28','172.203.190.128/28','172.203.190.80/28','20.125.66.80/28','20.14.99.96/28','20.168.18.32/28','20.169.6.224/28','20.169.7.48/28','20.169.77.0/25','20.171.123.64/28','20.171.206.0/24','20.171.207.0/24','20.171.53.224/28','20.25.151.224/28','20.42.10.176/28','23.102.145.48/28','4.227.36.0/25','40.67.175.0/25','40.90.214.16/28','51.8.102.0/24','52.230.152.0/24','74.7.175.128/25','74.7.227.0/25','74.7.227.128/25','74.7.228.0/25','74.7.228.128/25','74.7.229.0/25','74.7.229.128/25','74.7.230.0/25','74.7.241.0/25','74.7.241.128/25','74.7.242.0/25','74.7.242.128/25','74.7.243.0/25','74.7.243.128/25','74.7.244.0/25']::text[],ARRAY[]::text[],ARRAY['https://openai.com/gptbot.json','https://openai.com/searchbot.json']::text[]),
('anthropic','Anthropic',ARRAY['136.79.143.251/32','136.83.20.215/32','216.73.216.0/22','34.152.124.140/32','34.153.21.190/32','34.162.111.78/32','34.162.153.71/32','34.162.191.81/32','34.162.230.222/32','34.162.244.71/32','34.162.27.34/32','34.162.86.102/32','34.162.90.102/32','34.186.252.4/32']::text[],ARRAY[]::text[],ARRAY['https://claude.com/crawling/bots.json']::text[]),
('reflection','Reflection AI',ARRAY['16.216.88.0/23','16.216.94.0/23']::text[],ARRAY[]::text[],ARRAY['https://reflection.ai/bot/ips.json']::text[]),
('semrush','Semrush',ARRAY['85.208.96.0/22','185.191.171.0/24','185.170.167.0/24']::text[],ARRAY[]::text[],ARRAY['https://ipctl.io/as/209366']::text[]),
('meta','Meta',ARRAY['57.141.20.0/24']::text[],ARRAY[]::text[],ARRAY['https://ipregistry.co/AS32934/57.141.20.0/24']::text[]),
('you','You.com',ARRAY['68.67.112.0/24']::text[],ARRAY[]::text[],ARRAY['https://you.com/docs/youbot']::text[]),
('tencent','Tencent Cloud',ARRAY['43.160.0.0/12']::text[],ARRAY[]::text[],ARRAY['https://rdap.apnic.net/ip/43.173.180.1']::text[]),
('alibaba','Alibaba Cloud',ARRAY['47.79.13.0/24','47.82.11.0/24']::text[],ARRAY[]::text[],ARRAY['https://rdap.arin.net/registry/ip/47.79.13.1','https://rdap.arin.net/registry/ip/47.82.11.1']::text[]),
('huawei','Huawei Cloud',ARRAY['114.119.128.0/19']::text[],ARRAY[]::text[],ARRAY['https://rdap.apnic.net/ip/114.119.135.1']::text[]),
('code200','code200',ARRAY['152.163.44.0/22','152.163.72.0/22']::text[],ARRAY[]::text[],ARRAY['https://rdap.db.ripe.net/ip/152.163.46.1','https://rdap.db.ripe.net/ip/152.163.74.1']::text[]);

ALTER TABLE ip_whitelist ADD COLUMN source_id text REFERENCES traffic_source(id) ON DELETE SET NULL;
-- Preserve existing DNS verification; manual entries remain unclassified.
UPDATE ip_whitelist SET source_id = split_part(description, ' ', 1)
WHERE description IN (
 'googlebot verified reverse/forward DNS',
 'google verified reverse/forward DNS',
 'bingbot verified reverse/forward DNS',
 'yandex verified reverse/forward DNS'
);

-- Identity survives removal of a whitelist exemption.
CREATE TABLE ip_source (
 ip inet PRIMARY KEY,
 source_id text NOT NULL REFERENCES traffic_source(id) ON DELETE CASCADE
);
INSERT INTO ip_source (ip,source_id)
 SELECT ip,source_id FROM ip_whitelist WHERE source_id IS NOT NULL;
