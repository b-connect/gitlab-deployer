mkdir /root/.ssh
echo "$SSH_KEY" > /root/.ssh/id_rsa
chmod 700 /root/.ssh/id_rsa
cd /
export PATH="%PATH:/vendor/bin:/usr/local/bin:/usr/bin"
touch log
# tail -f log &
# script -e -c "dep deploy --tag=${CI_COMMIT_TAG}" log
if [[ -z "$CI_COMMIT_TAG" ]]
  then
    dep deploy --tag="${CI_COMMIT_TAG}"
  else
    dep deploy --revision=${CI_COMMiT_SHA}
fi;
