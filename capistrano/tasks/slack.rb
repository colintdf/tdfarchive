# Capistrano Task that hooks into `deploy:finished` to send a message to Slack
#
# 1. Setup a Slack "Incoming Webhook Integration" (https://api.slack.com/incoming-webhooks)
# 2. Define the URL in your deploy.rb eg. set :slack_hook, 'https://www...'
# 3. Place this file in `capistrano/tasks`
#
# This will then create a new message in the channel on deployment, including who, what and where information

require "net/http"
require "json"
require "uri"

namespace :slack do
	desc 'Notify Slack of a deployment'
	task :notify do
		slack_hook = fetch(:slack_hook)
		if slack_hook
			stage = fetch(:stage)
			msg = "#{fetch(:site_url)} *#{stage}*: #{revision_log_message}"

			payload = {
				username: fetch(:application),
				text: msg,
				mrkdwn: true,
			}.to_json

			Net::HTTP.post_form(URI.parse(slack_hook), payload: payload)
		end
	end
end
