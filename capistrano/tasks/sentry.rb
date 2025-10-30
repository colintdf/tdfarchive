namespace :sentry do
    task :notify_deployment do
      run_locally do
        require 'uri'
        require 'net/https'
  
        puts "Notifying Sentry of release..."
        uri = 'sentry.io'
        http = Net::HTTP.new(uri, 443)
        http.use_ssl = true
        req = Net::HTTP::Post.new("/api/0/projects/#{fetch(:sentry_organization)}/#{fetch(:sentry_project)}/releases/", initheader={'Content-Type' =>'application/json'})
        req['Authorization'] = "Bearer #{fetch(:sentry_api_token)}"
        req.body = %Q[{"version":"#{fetch(:release_timestamp)}","ref":"#{fetch(:current_revision)}","branch":"#{fetch(:branch)}","environment":"#{fetch(:sentry_env)}"}]
  
        response = http.start { |h| h.request(req) }
        puts "Sentry response: #{response.body}"
      end
    end
  end
  
