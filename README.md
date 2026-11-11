# Diagral Local for OPNsense

`os-diagral-local` provides manual routing between a Diagral gateway's normal cloud service and a separately operated local bridge. It does not emulate the Diagral protocol, send alarm commands, change DNS, or automatically switch modes.

## Interface

Open **Services → Diagral Local**. Configure the gateway MAC address, the gateway-facing interface and the local bridge IPv4 address. The bridge must listen on TCP 6000 on the same network as that interface.

Saving settings does not apply routing. Select **Local** to redirect this gateway's public IPv4 TCP 6000 connections to the bridge. Private/reserved destinations and other source devices are excluded. Select **Cloud** to remove the plugin's rules. Only the matching gateway TCP 6000 states are cleared during a mode change. No mode change occurs during package installation or upgrade.

Select Cloud before changing an active configuration, then save the new settings and select Local. A mismatched configuration or incomplete loaded rules returns `unknown`, not a successful switch. Status checks do not mutate routing.

## API

Native OPNsense authentication and CSRF protections apply. Grant a dedicated API user only **Services: Diagral Local routing control**:

- `GET /api/diagrallocal/service/status`
- `POST /api/diagrallocal/service/local`
- `POST /api/diagrallocal/service/cloud`

Responses include `mode` (`local`, `cloud`, `unknown`) and `states_cleared`. Clients must treat malformed responses, timeouts and `unknown` as unconfirmed; do not replay commands or infer a fallback. Store API credentials outside source control and verify TLS certificates.

The configuration privilege is separate from the routing-control privilege. An existing restricted SSH client can invoke `/usr/local/bin/php /usr/local/opnsense/scripts/diagrallocal/routing_control.php` with a forced SSH command limited to `status`, `local`, or `cloud`. The package does not install SSH keys.

## Signed package repository

Validated with OPNsense 26.7 / FreeBSD 15 amd64. The repository contains only this plugin. Install the public signing key `repository-public.pem` at `/usr/local/etc/pkg/keys/sdskh-diagral.pub` and `SDSKHDiagral.conf` at `/usr/local/etc/pkg/repos/SDSKHDiagral.conf`. Then refresh Firmware and install `os-diagral-local`. Verify the key fingerprint against an independently trusted copy before first installation. The private signing key is never distributed.

Future major OPNsense versions or ABI changes require a compatible build/catalogue. GitHub hosting alone does not guarantee compatibility. Package updates use the native signed `pkg` mechanism, not `git pull` over system files.

## Persistence and removal

Settings are stored in `config.xml` under `OPNsense/DiagralLocal`; routing rules are native NAT/alias configuration. No periodic daemon or startup command is needed to recreate active rules. Package scripts live under `/usr/local/opnsense/scripts/diagrallocal`.

Removal deliberately preserves the current native rules and settings to avoid an unexpected cloud reconnection. **Select Cloud before uninstalling** if you want to remove the redirection. Remove any manually created restricted SSH entry or compatibility wrapper separately. Existing non-plugin objects must not use the reserved `DIAGRAL_GATEWAY_MAC` / `DIAGRAL_INTERNAL_NETWORKS` aliases or the plugin's rule UUIDs.

High-availability deployment requires the package on every node, synchronized settings/rules and a reachable control endpoint. Failover has not been validated by this release. A single-node installation does not claim automatic failover support.

## Build

Run `sh build.sh` on the target OPNsense ABI. This produces a local package without installing it. Sign the repository using native `pkg repo` and a private key kept outside the repository. PHP syntax, XML parsing and six selection-policy cases were checked; live menu, authenticated status API and existing consumer continuity were checked without issuing alarm commands. A complete firewall reboot and routing changes through the new UI have not been tested.

Independent project, not an official Diagral or OPNsense plugin.

