# Go-live checklist and rollback plan

## Before go-live

- [ ] All items in [OPEN-QUESTIONS.md](OPEN-QUESTIONS.md) answered
- [ ] Sample products replaced; `DOTCOMSA_SAMPLE_DATA` set to `false`
- [ ] `config.js` checked: phone, WhatsApp, email, delivery fee
- [ ] Logo and real photos in place
- [ ] `python3 tools/site_test.py` passes on the final version
- [ ] Manual check on a real Android phone and an iPhone: place a test order on WhatsApp end to end
- [ ] Deployed to Xneelo and fully tested there
- [ ] Card payments: every step in PAYMENTS-SETUP.md done, sandbox tests passed, one real payment and refund checked in both PayFast accounts
- [ ] `api/config.local.php` on the live site has live PayFast details and `sandbox => false`
- [ ] Database backup set up in Xneelo and a restore tested
- [ ] Client sign-off by Joseph Mathenjwa (Gate 2)
- [ ] Dion's written go-ahead (Gate 3)
- [ ] Domain pointed to Xneelo; HTTPS certificate active
- [ ] Release tagged in GitHub (e.g. `v1.0.0`) and RELEASE-NOTES.md updated

## Rollback plan

The site is plain files, so rollback is quick and safe.

1. Every release is tagged in GitHub.
2. If a release breaks something, re-upload the files from the previous tag to Xneelo (File Manager or FTP).
3. Re-run `tools/site_test.py` against the live site to confirm it's fixed.
4. Log what happened in RELEASE-NOTES.md.

Phase 1 has no database. With Phase 2, files roll back the same way, the database is never rolled back (orders must be kept), and `api/config.local.php` stays in place.
