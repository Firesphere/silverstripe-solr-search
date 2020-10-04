# Installing Solr

It is advised to use the [latest Solr version](https://downloads.apache.org/lucene/solr/). At the time of writing, this is version 8.3.0.

In this guide, it's assumed you are running on a Linux-based host.

## Requirements

### Java

#### Debian based distributions

`sudo apt install default-jre`

#### Debian Jessie

Debian Jessie needs backports to get Java 8 working:
```bash
echo "deb [check-valid-until=no] http://archive.debian.org/debian jessie-backports main" > /etc/apt/sources.list.d/jessie-backports.list
apt-get update
apt-get install -t jessie-backports openjdk-8-jre
```

If you run in to trouble updating, add the following to `/etc/apt/apt.conf`:
- `Acquire::Check-Valid-Until "false";`

## Installation

Taken from https://lucene.apache.org/solr/guide/7_7/taking-solr-to-production.html

Update to match the required version. [You can find the latest version here](https://www-us.apache.org/dist/lucene/solr/)
```bash
wget http://www.apache.org/dyn/closer.lua/lucene/solr/8.3.1/solr-8.3.1.tgz # find your local URL manually
tar xvf solr-8.3.1.tgz solr-8.3.1/bin/install_solr_service.sh --strip-components=2
sudo bash ./install_solr_service.sh solr-8.3.1.tgz
```

Be sure to download the tgz, not the src.tgz file.

This will install Solr 8.x as a service on your host machine

## Vagrant machines

See [Known issues](14-Help/03-Known-issues.md) for known issues with permissions
