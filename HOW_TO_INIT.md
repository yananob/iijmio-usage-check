# for Cloud Functions Apps

```
# submodule
git submodule add git@github.com:yananob/cloud-functions-common _cf-common
git submodule add git@github.com:yananob/_template-cloudfunctions _template
```

## GitHub actionでのデプロイ

1. 以下見る https://console.cloud.google.com/projectselector2/iam-admin/serviceaccounts?hl=ja&inv=1&invt=Abzjyw&supportedpurview=project
2. 既存のリポジトリの内容を参考に、同じように追加する
    - Cloud Run Deployer SAに、既存のプリンシプル〜タブで追加する
コマンドでもできるはずだが、エラーになるので、上記GUIでの設定にしている

## 自動PR作成の許可
GitHub ActionsがPRを作成できるようにするために、リポジトリの設定で以下を有効にする必要があります。
1. リポジトリの **Settings** > **Actions** > **General** を開く
2. **Workflow permissions** セクションで **Allow GitHub Actions to create and approve pull requests** にチェックを入れる
3. **Save** をクリック
