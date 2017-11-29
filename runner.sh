mkdir /root/.ssh
echo "$SSH_KEY" > /root/.ssh/id_rsa
chmod 700 /root/.ssh/id_rsa
cd /
export PATH="%PATH:/vendor/bin:/usr/local/bin:/usr/bin"

echo "DEPLOY TO  ${CI_ENVIRONMENT_URL}"

if [[ -z "$CI_COMMIT_TAG" ]]
  then
    dep deploy --revision=${CI_COMMiT_SHA}
  else
    dep deploy --tag="${CI_COMMIT_TAG}"
fi;
